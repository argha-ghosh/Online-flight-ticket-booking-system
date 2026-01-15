<?php
include("../includes/adminheader.php");
// include("../controller/AirlineController.php");

// Get the airline ID from the URL parameter
$id = $_GET['id']; // Ensure this is passed in the URL

// Fetch the airline data from the database
$sql = "SELECT * FROM airlines WHERE id = $id";
$result = $conn->query($sql);
$airline = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Airline</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to your CSS file -->
</head>
<body>

<div class="container">
    <!-- Edit Airline Form -->
    <div class="form-box">
        <h2>Edit Airline</h2>

        <form action="editAirline.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $airline['id'] ?>"> <!-- Hidden input for airline ID -->
            <input type="text" name="airline_name" value="<?= $airline['airline_name'] ?>" placeholder="Enter Airline Name" class="box" required><br><br>
            <input type="text" name="country_name" value="<?= $airline['country_name'] ?>" placeholder="Enter Country Name" class="box" required><br><br>
            <input type="text" name="airline_code" value="<?= $airline['airline_code'] ?>" placeholder="Enter Airline Code" class="box" required><br><br>
            <textarea name="airline_details" placeholder="Enter Airline Details" class="box" required><?= $airline['airline_details'] ?></textarea><br><br>

            <!-- Display current image -->
            <div>
                <img src="<?= $airline['image'] ?>" width="100" alt="Current Airline Image"><br><br>
                <label>Change Image (Optional)</label><br>
                <input type="file" name="image" class="box"><br><br>
            </div>

            <button type="submit" name="update" class="btn">Update Airline</button>
        </form>
    </div>
</div>

</body>
</html>
