<?php
include("../includes/adminheader.php");
include("../controller/AirlineController.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Airline</title>
    <link rel="stylesheet" href="flightstyle.css">
    <script src="../controller/airlineValidation.js"></script>
</head>

<div class="container">
    <!-- Add Airline Form -->
    <div class="form-box">
        <h2>Add New Airline</h2>

        <form action="addAirline.php" method="POST" enctype="multipart/form-data" id="airlineForm">
            <div id="errorMessages" style="color: red; margin-bottom: 10px;"></div>
            <input type="text" name="airline_name" id="airline_name" placeholder="Enter Airline Name" class="box" required><br><br>
            <input type="text" name="country_name" id="country_name" placeholder="Enter Country Name" class="box" required><br><br>
            <input type="text" name="airline_code" id="airline_code" placeholder="Enter Airline Code" class="box" required><br><br>
            <textarea name="airline_details" id="airline_details" placeholder="Enter Airline Details" class="box" required></textarea><br><br>
            <input type="file" name="image" id="image" class="box" required accept="image/*"><br><br>
            <button type="submit" name="submit" class="btn">Add Airline</button>
        </form>
    </div>

    <!-- Existing Airlines List -->
    <br>
    <h2>Existing Airlines</h2>
    <div class="flight-list">
        <?php foreach ($airlines as $airline) { ?>
            <div class="flight-box">
                <img src="onload/<?= basename($airline['image']) ?>" width="100">
                <h3><?= $airline['airline_name'] ?></h3>
                <p><b>Country:</b> <?= $airline['country_name'] ?></p>
                <p><b>Code:</b> <?= $airline['airline_code'] ?></p>
                <p><b>Details:</b> <?= $airline['airline_details'] ?></p>

                <a href="editAirline.php?id=<?= $airline['id'] ?>" class="btn edit-btn">Edit</a> |
                <a href="?delete_id=<?= $airline['id'] ?>" class="btn delete-btn" onclick="return confirm('Delete this airline?')">Delete</a>
            </div>
        <?php } ?>
    </div>
</div>

</body>
</html>

<?php
include("../includes/footer.php");  
?>