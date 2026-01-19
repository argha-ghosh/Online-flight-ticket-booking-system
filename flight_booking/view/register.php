<?php
include("../model/db_conn.php");
include("../includes/header.php");

$success_message = "";
$error_message = "";

if (isset($_POST['submit'])) {
    
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['pass'];
    $confirm_password = $_POST['cpass'];
    
    // Validate password match
    if ($password !== $confirm_password) {
        $error_message = "Error: Passwords do not match!";
    } else {
        // Check if email already exists in webusers table
        $check_query = "SELECT id FROM webusers WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Error: Email already exists!";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Handle image upload
            $image = "";
            if (!empty($_FILES['image']['name'])) {
                $upload_dir = __DIR__ . "/uploads/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $image = time() . '_' . basename($_FILES['image']['name']);
                $target_file = $upload_dir . $image;
                
                // Validate image type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                if (in_array($_FILES['image']['type'], $allowed_types)) {
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        // Image uploaded successfully
                    } else {
                        $error_message = "Error: Failed to upload image.";
                    }
                } else {
                    $error_message = "Error: Invalid image type. Only JPEG and PNG are allowed.";
                }
            } else {
                $error_message = "Error: Please select a profile image.";
            }
            
            // If no errors, insert into database
            if (empty($error_message)) {
                // Insert into webusers table (matching your table structure: id, name, email, pass, cpass, image)
                // Note: cpass is not stored, only used for validation
                $sql_webusers = "INSERT INTO webusers (name, email, pass, image) 
                             VALUES (?, ?, ?, ?)";
                $stmt_webusers = $conn->prepare($sql_webusers);
                $stmt_webusers->bind_param("ssss", $name, $email, $hashed_password, $image);
                
                // Also insert into login table for authentication
                $sql_login = "INSERT INTO login (email, password, role) VALUES (?, ?, 'webuser')";
                $stmt_login = $conn->prepare($sql_login);
                $stmt_login->bind_param("ss", $email, $hashed_password);
                
                if ($stmt_webusers->execute() && $stmt_login->execute()) {
                    $success_message = "Registration successful! You can now login.";
                    // Clear form data
                    $_POST = array();
                } else {
                    $error_message = "Error: " . mysqli_error($conn);
                }
                
                $stmt_webusers->close();
                $stmt_login->close();
            }
        }
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
    <link rel="stylesheet" href="register.css">
</head>
<body>
    <section class="register">
        <form action="" enctype="multipart/form-data" method="post" id="registerForm" novalidate>
            <div id="errorMessages" style="color: red; margin-bottom: 10px;"></div>
            
            <?php if ($success_message != "") { ?>
                <div style="color: green; margin-bottom: 10px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">
                    <?php echo $success_message; ?>
                </div>
            <?php } ?>
            
            <?php if ($error_message != "") { ?>
                <div style="color: red; margin-bottom: 10px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;">
                    <?php echo $error_message; ?>
                </div>
            <?php } ?>
            
            <h3>Register Now</h3>
            <input type="text" name="name" id="name" placeholder="Enter your name" class="box" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required> 
            <input type="email" name="email" id="email" placeholder="Enter your email" class="box" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required> 
            <input type="password" name="pass" id="pass" placeholder="Enter your password" class="box" required> 
            <input type="password" name="cpass" id="cpass" placeholder="Confirm your password" class="box" required> 
            <input type="file" name="image" id="image" accept="image/jpg, image/jpeg, image/png" class="box" required> 
            <input type="submit" value="register now" class="btn" name="submit">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </section>

    <script src="../controller/registerValidation.js"></script>
</body>
</html>

<?php
include("../includes/footer.php");  
?>