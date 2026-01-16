<?php
include("../includes/adminheader.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../model/FlightModel.php";
$flightModel = new FlightModel();

$flight = $flightModel->getFlightById($_GET['id']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Flight</title>
</head>
<body>

<h2>Edit Flight</h2>

<form action="../controller/FlightController.php" method="POST" enctype="multipart/form-data" id="editFlightForm">

    <div id="errorMessages" style="color: red; margin-bottom: 10px;"></div>

    <input type="hidden" name="id" value="<?= $flight['id'] ?>">
    <input type="hidden" name="old_image" value="<?= $flight['image'] ?>">

    Flight Name:
    <input type="text" name="flight_name" id="flight_name" value="<?= $flight['flight_name'] ?>" required><br><br>

    Airline Name:
    <input type="text" name="airline_name" id="airline_name" value="<?= $flight['airline_name'] ?>" required><br><br>

    Flight Code:
    <input type="text" name="flight_code" id="flight_code" value="<?= $flight['flight_code'] ?>" required><br><br>

    Departure:
    <input type="text" name="departure" id="departure" value="<?= $flight['departure'] ?>" required><br><br>

    Arrival:
    <input type="text" name="arrival" id="arrival" value="<?= $flight['arrival'] ?>" required><br><br>

    Duration:
    <input type="text" name="duration" id="duration" value="<?= $flight['duration'] ?>" required><br><br>

    Price:
    <input type="number" step="0.01" name="price" id="price" value="<?= $flight['price'] ?>" required><br><br>

    Current Image:<br>
    <img src="upload/<?= $flight['image'] ?>" width="120"><br><br>

    Change Image:
    <input type="file" name="image" id="image" accept="image/*"><br><br>

    <button type="submit" name="update">Update Flight</button>
</form>

<script src="../controller/editFlightValidation.js"></script>

</body>
</html>
