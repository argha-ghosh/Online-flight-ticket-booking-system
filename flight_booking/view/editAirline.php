<?php
include("../includes/adminheader.php");
include("../model/db_conn.php");

// Handle update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $airline_name = $_POST['airline_name'];
    $country_name = $_POST['country_name'];
    $airline_code = $_POST['airline_code'];
    $airline_details = $_POST['airline_details'];

    // Handle image upload if new image is provided
    if (!empty($_FILES['image']['name'])) {
        $image_name = $_FILES['image']['name'];
        $image_temp = $_FILES['image']['tmp_name'];
        $image_path = '../view/onload/' . $image_name;
        move_uploaded_file($image_temp, $image_path);
        $image_update = ", image = '$image_name'";
    } else {
        $image_update = "";
    }

    $sql = "UPDATE airlines SET airline_name = '$airline_name', country_name = '$country_name', airline_code = '$airline_code', airline_details = '$airline_details' $image_update WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: addAirline.php"); // Redirect back to list
        exit;
    } else {
        echo "Error updating record: " . $conn->error;
    }
}

// Get the airline ID from the URL parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the airline data from the database
$sql = "SELECT * FROM airlines WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$airline = $result->fetch_assoc();

if (!$airline) {
    die("Airline not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Airline</title>
</head>
<body>

<div class="container">
    <!-- Edit Airline Form -->
    <div class="form-box">
        <h2>Edit Airline</h2>

        <form action="editAirline.php" method="POST" enctype="multipart/form-data" id="editAirlineForm">
            <div id="errorMessages" style="color: red; margin-bottom: 10px;"></div>
            <input type="hidden" name="id" value="<?= $airline['id'] ?>"> <!-- Hidden input for airline ID -->
            <input type="text" name="airline_name" id="airline_name" value="<?= $airline['airline_name'] ?>" placeholder="Enter Airline Name" class="box" required><br><br>
            <input type="text" name="country_name" id="country_name" value="<?= $airline['country_name'] ?>" placeholder="Enter Country Name" class="box" required><br><br>
            <input type="text" name="airline_code" id="airline_code" value="<?= $airline['airline_code'] ?>" placeholder="Enter Airline Code" class="box" required><br><br>
            <textarea name="airline_details" id="airline_details" placeholder="Enter Airline Details" class="box" required><?= $airline['airline_details'] ?></textarea><br><br>

            <!-- Display current image -->
            <div>
                <img src="onload/<?= basename($airline['image']) ?>" width="100" alt="Current Airline Image"><br><br>
                <label>Change Image (Optional)</label><br>
                <input type="file" name="image" id="image" class="box" accept="image/*"><br><br>
            </div>

            <button type="submit" name="update" class="btn">Update Airline</button>
        </form>
    </div>
</div>

<script src="../controller/editAirlineValidation.js"></script>

</body>
</html>
