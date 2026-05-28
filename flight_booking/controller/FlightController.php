<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../model/FlightModel.php";
$flightModel = new FlightModel();

// ── Add Flight ───────────────────────────────────────────────
if (isset($_POST['submit'])) {

    $imageName = time() . '_' . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], "../view/upload/" . $imageName);

    $data = [
        'flight_name'    => trim($_POST['flight_name']),
        'airline_name'   => trim($_POST['airline_name']),
        'flight_code'    => trim($_POST['flight_code']),
        'departure'      => trim($_POST['departure']),
        'arrival'        => trim($_POST['arrival']),
        'departure_time' => $_POST['departure_time'],
        'arrival_time'   => $_POST['arrival_time'],
        'duration'       => trim($_POST['duration']),
        'price'          => (float)$_POST['price'],
        'flight_class'   => $_POST['flight_class'],
        'total_seats'    => (int)$_POST['total_seats'],
        'status'         => in_array($_POST['status'], ['active','inactive','cancelled']) ? $_POST['status'] : 'active',
        'image'          => $imageName,
    ];

    if ($flightModel->addFlight($data)) {
        $_SESSION['flight_msg']      = 'Flight added successfully!';
        $_SESSION['flight_msg_type'] = 'success';
    } else {
        $_SESSION['flight_msg']      = 'Error adding flight.';
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: /flight_booking/view/addFlight.php");
    exit;
}

// ── Update Flight ────────────────────────────────────────────
if (isset($_POST['update'])) {

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "../view/upload/" . $imageName);
    } else {
        $imageName = $_POST['old_image'];
    }

    $data = [
        'id'             => (int)$_POST['id'],
        'flight_name'    => trim($_POST['flight_name']),
        'airline_name'   => trim($_POST['airline_name']),
        'flight_code'    => trim($_POST['flight_code']),
        'departure'      => trim($_POST['departure']),
        'arrival'        => trim($_POST['arrival']),
        'departure_time' => $_POST['departure_time'],
        'arrival_time'   => $_POST['arrival_time'],
        'duration'       => trim($_POST['duration']),
        'price'          => (float)$_POST['price'],
        'flight_class'   => $_POST['flight_class'],
        'total_seats'    => (int)$_POST['total_seats'],
        'status'         => in_array($_POST['status'], ['active','inactive','cancelled']) ? $_POST['status'] : 'active',
        'image'          => $imageName,
    ];

    if ($flightModel->updateFlight($data)) {
        $_SESSION['flight_msg']      = 'Flight updated successfully!';
        $_SESSION['flight_msg_type'] = 'success';
    } else {
        $_SESSION['flight_msg']      = 'Error updating flight.';
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: /flight_booking/view/addFlight.php");
    exit;
}

// ── Delete Flight ────────────────────────────────────────────
if (isset($_GET['delete_id'])) {

    $id     = (int)$_GET['delete_id'];
    $flight = $flightModel->getFlightById($id);

    if ($flight && !empty($flight['image'])) {
        $path = "../view/upload/" . $flight['image'];
        if (file_exists($path)) unlink($path);
    }

    if ($flightModel->deleteFlight($id)) {
        $_SESSION['flight_msg']      = 'Flight deleted successfully.';
        $_SESSION['flight_msg_type'] = 'success';
    } else {
        $_SESSION['flight_msg']      = 'Error deleting flight.';
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: /flight_booking/view/addFlight.php");
    exit;
}
?>
