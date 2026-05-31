<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . "/../config/base_url.php";
}

require_once __DIR__ . "/../model/FlightModel.php";
$flightModel = new FlightModel();

// ── Add Flight ───────────────────────────────────────────────
if (isset($_POST['submit'])) {

    $uploadDir = __DIR__ . "/../view/upload/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $imageName = time() . '_' . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);

    $data = [
        'flight_name'    => trim($_POST['flight_name']),
        'airline_name'   => trim($_POST['airline_name']),
        'flight_code'    => trim($_POST['flight_code']),
        'departure'      => trim($_POST['departure']),
        'arrival'        => trim($_POST['arrival']),
        'departure_time' => $_POST['departure_time']  ?? '00:00',
        'arrival_time'   => $_POST['arrival_time']    ?? '00:00',
        'duration'       => trim($_POST['duration']),
        'price'          => (float)$_POST['price'],
        'flight_class'   => in_array($_POST['flight_class'] ?? '', ['Economy','Business','First Class'])
                            ? $_POST['flight_class'] : 'Economy',
        'seat_class'     => in_array($_POST['seat_class'] ?? '', ['Economy','Business','First Class'])
                            ? $_POST['seat_class'] : 'Economy',
        'total_seats'    => (int)($_POST['total_seats'] ?? 180),
        'seat'           => (int)($_POST['total_seats'] ?? 180), // available = total on creation
        'discount_pct'   => (float)($_POST['discount_pct'] ?? 0),
        'status'         => in_array($_POST['status'] ?? '', ['active','inactive','cancelled'])
                            ? $_POST['status'] : 'active',
        'image'          => $imageName,
    ];

    if ($flightModel->addFlight($data)) {
        $_SESSION['flight_msg']      = 'Flight added successfully!';
        $_SESSION['flight_msg_type'] = 'success';
    } else {
        $_SESSION['flight_msg']      = 'Error: ' . $flightModel->getLastError();
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: " . BASE_URL . "/view/addFlight.php");
    exit;
}

// ── Update Flight ────────────────────────────────────────────
if (isset($_POST['update'])) {

    $uploadDir = __DIR__ . "/../view/upload/";
    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
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
        'departure_time' => $_POST['departure_time']  ?? '00:00',
        'arrival_time'   => $_POST['arrival_time']    ?? '00:00',
        'duration'       => trim($_POST['duration']),
        'price'          => (float)$_POST['price'],
        'flight_class'   => in_array($_POST['flight_class'] ?? '', ['Economy','Business','First Class'])
                            ? $_POST['flight_class'] : 'Economy',
        'seat_class'     => in_array($_POST['seat_class'] ?? '', ['Economy','Business','First Class'])
                            ? $_POST['seat_class'] : 'Economy',
        'total_seats'    => (int)($_POST['total_seats'] ?? 180),
        'seat'           => (int)($_POST['seat']        ?? 0),
        'discount_pct'   => (float)($_POST['discount_pct'] ?? 0),
        'status'         => in_array($_POST['status'] ?? '', ['active','inactive','cancelled'])
                            ? $_POST['status'] : 'active',
        'image'          => $imageName,
    ];

    if ($flightModel->updateFlight($data)) {
        $_SESSION['flight_msg']      = 'Flight updated successfully!';
        $_SESSION['flight_msg_type'] = 'success';
    } else {
        $_SESSION['flight_msg']      = 'Error: ' . $flightModel->getLastError();
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: " . BASE_URL . "/view/addFlight.php");
    exit;
}

// ── Delete Flight ────────────────────────────────────────────
if (isset($_GET['delete_id'])) {

    $id     = (int)$_GET['delete_id'];
    $flight = $flightModel->getFlightById($id);

    if ($flight && !empty($flight['image'])) {
        $path = __DIR__ . "/../view/upload/" . $flight['image'];
        if (file_exists($path)) unlink($path);
    }

    if ($flightModel->deleteFlight($id)) {
        $_SESSION['flight_msg']      = 'Flight deleted successfully.';
        $_SESSION['flight_msg_type'] = 'success';
    } else {
        $_SESSION['flight_msg']      = 'Error: ' . $flightModel->getLastError();
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: " . BASE_URL . "/view/addFlight.php");
    exit;
}
?>
