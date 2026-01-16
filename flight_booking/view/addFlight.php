<?php
include("../model/db_conn.php");
include("../includes/adminheader.php");
?>

<?php
require_once "../model/FlightModel.php";
$flightModel = new FlightModel();
$flights = $flightModel->getAllFlights();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Flight</title>
    <link rel="stylesheet" href="flightstyle.css"> 
</head>
<body>

<div class="container">

    <!-- ADD FLIGHT FORM -->
    <div class="form-box">
        <h2>Add New Flight</h2>
        <form action="../controller/FlightController.php" method="POST" enctype="multipart/form-data" id="addFlightForm">
            <div id="errorMessages" style="color: red; margin-bottom: 10px;"></div>
            <input type="text" name="flight_name" id="flight_name" placeholder="Enter Flight Name" class="box" required><br><br>
            <input type="text" name="airline_name" id="airline_name" placeholder="Enter Airline Name" class="box" required><br><br>
            <input type="text" name="flight_code" id="flight_code" placeholder="Enter Flight Code" class="box" required><br><br>
            <input type="text" name="departure" id="departure" placeholder="Enter Departure From" class="box" required><br><br>
            <input type="text" name="arrival" id="arrival" placeholder="Enter Arrival To" class="box" required><br><br>
            <input type="text" name="duration" id="duration" placeholder="Enter Time Duration" class="box" required><br><br>
            <input type="number" step="0.01" name="price" id="price" placeholder="Enter Price" class="box" required><br><br>
            <input type="file" name="image" id="image" class="box" required accept="image/*"><br><br>

            <button type="submit" name="submit" class="btn">Add Flight</button>
        </form>
    </div>

    <!-- FLIGHT LIST -->
    <h2>Existing Flights</h2>

    <div class="flight-list">
        <?php while ($row = $flights->fetch_assoc()) { ?>
            <div class="flight-box">
                <img src="upload/<?= $row['image'] ?>" alt="Flight Image">
                <h3><?= $row['flight_name'] ?></h3>
                <p><b>Airline Name:</b> <?= $row['airline_name'] ?></p>
                <p><b>Airline Code:</b> <?= $row['flight_code'] ?></p>
                <p><b>From:</b> <?= $row['departure'] ?></p>
                <p><b>To:</b> <?= $row['arrival'] ?></p>
                <p><b>Duration:</b> <?= $row['duration'] ?></p>
                <p><b>Price:</b> ₹<?= $row['price'] ?></p>

                <a href="editFlight.php?id=<?= $row['id'] ?>" class="btn edit-btn">Edit</a> |
                <a href="../controller/FlightController.php?delete_id=<?= $row['id'] ?>" class="btn delete-btn" onclick="return confirm('Delete this flight?')">Delete</a>
            </div>
        <?php } ?>
    </div>

</div>

<script src="../controller/addFlightValidation.js"></script>

</body>
</html>

<?php
include("../includes/footer.php");
?>
