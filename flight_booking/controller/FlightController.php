<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../model/FlightModel.php";
$flightModel = new FlightModel();

/* ADD FLIGHT */
if (isset($_POST['submit'])) {

    $imageName = time() . "_" . $_FILES['image']['name'];
    move_uploaded_file($_FILES['image']['tmp_name'], "../view/upload/" . $imageName);

    $data = [
        'flight_name' => $_POST['flight_name'],
        'airline_name' => $_POST['airline_name'],
        'flight_code' => $_POST['flight_code'],
        'departure' => $_POST['departure'],
        'arrival' => $_POST['arrival'],
        'duration' => $_POST['duration'],
        'price' => $_POST['price'],
        'image' => $imageName
    ];

    $flightModel->addFlight($data);
    header("Location: ../view/addFlight.php");
    exit;
}

/* UPDATE FLIGHT */
if (isset($_POST['update'])) {

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "../view/upload/" . $imageName);
    } else {
        $imageName = $_POST['old_image'];
    }

    $data = [
        'id' => (int)$_POST['id'],
        'flight_name' => $_POST['flight_name'],
        'airline_name' => $_POST['airline_name'],
        'flight_code' => $_POST['flight_code'],
        'departure' => $_POST['departure'],
        'arrival' => $_POST['arrival'],
        'duration' => $_POST['duration'],
        'price' => $_POST['price'],
        'image' => $imageName
    ];

    $flightModel->updateFlight($data);
    header("Location: ../view/addFlight.php");
    exit;
}

/* DELETE FLIGHT */
if (isset($_GET['delete_id'])) {

    $id = (int)$_GET['delete_id'];
    $flight = $flightModel->getFlightById($id);

    if ($flight && !empty($flight['image'])) {
        $imagePath = "../view/upload/" . $flight['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $flightModel->deleteFlight($id);
    header("Location: ../view/addFlight.php");
    exit;
}
