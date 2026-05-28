<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../model/db_conn.php");

// ── Add new airline ──────────────────────────────────────────
if (isset($_POST['submit'])) {
    $airline_name    = $conn->real_escape_string(trim($_POST['airline_name']));
    $country_name    = $conn->real_escape_string(trim($_POST['country_name']));
    $airline_code    = $conn->real_escape_string(trim($_POST['airline_code']));
    $airline_details = $conn->real_escape_string(trim($_POST['airline_details']));
    $website         = $conn->real_escape_string(trim($_POST['website'] ?? ''));
    $founded_year    = !empty($_POST['founded_year']) ? (int)$_POST['founded_year'] : 'NULL';
    $fleet_size      = !empty($_POST['fleet_size'])   ? (int)$_POST['fleet_size']   : 'NULL';
    $status          = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    $image_name = basename($_FILES['image']['name']);
    $image_temp = $_FILES['image']['tmp_name'];
    $image_path = '../view/onload/' . $image_name;
    move_uploaded_file($image_temp, $image_path);

    $founded_val = ($founded_year === 'NULL') ? 'NULL' : $founded_year;
    $fleet_val   = ($fleet_size   === 'NULL') ? 'NULL' : $fleet_size;

    $sql = "INSERT INTO airlines
                (airline_name, country_name, airline_code, airline_details, image, website, founded_year, fleet_size, status)
            VALUES
                ('$airline_name', '$country_name', '$airline_code', '$airline_details', '$image_name',
                 '$website', $founded_val, $fleet_val, '$status')";

    if ($conn->query($sql) === TRUE) {
        $_SESSION['airline_msg']      = 'Airline added successfully!';
        $_SESSION['airline_msg_type'] = 'success';
    } else {
        $_SESSION['airline_msg']      = 'Error adding airline: ' . $conn->error;
        $_SESSION['airline_msg_type'] = 'error';
    }

    header("Location: /flight_booking/view/addAirline.php");
    exit;
}

// ── Delete airline ───────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    if ($conn->query("DELETE FROM airlines WHERE id = $delete_id") === TRUE) {
        $_SESSION['airline_msg']      = 'Airline deleted successfully.';
        $_SESSION['airline_msg_type'] = 'success';
    } else {
        $_SESSION['airline_msg']      = 'Error deleting airline: ' . $conn->error;
        $_SESSION['airline_msg_type'] = 'error';
    }

    header("Location: /flight_booking/view/addAirline.php");
    exit;
}

// ── Update airline ───────────────────────────────────────────
if (isset($_POST['update'])) {
    $id              = (int) $_POST['id'];
    $airline_name    = $conn->real_escape_string(trim($_POST['airline_name']));
    $country_name    = $conn->real_escape_string(trim($_POST['country_name']));
    $airline_code    = $conn->real_escape_string(trim($_POST['airline_code']));
    $airline_details = $conn->real_escape_string(trim($_POST['airline_details']));
    $website         = $conn->real_escape_string(trim($_POST['website'] ?? ''));
    $founded_year    = !empty($_POST['founded_year']) ? (int)$_POST['founded_year'] : 'NULL';
    $fleet_size      = !empty($_POST['fleet_size'])   ? (int)$_POST['fleet_size']   : 'NULL';
    $status          = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    $founded_val = ($founded_year === 'NULL') ? 'NULL' : $founded_year;
    $fleet_val   = ($fleet_size   === 'NULL') ? 'NULL' : $fleet_size;

    $image_name = basename($_FILES['image']['name']);
    if ($image_name !== '') {
        move_uploaded_file($_FILES['image']['tmp_name'], '../view/onload/' . $image_name);
        $img_sql = ", image='$image_name'";
    } else {
        $img_sql = '';
    }

    $update_sql = "UPDATE airlines SET
                    airline_name='$airline_name', country_name='$country_name',
                    airline_code='$airline_code', airline_details='$airline_details',
                    website='$website', founded_year=$founded_val, fleet_size=$fleet_val,
                    status='$status' $img_sql
                   WHERE id=$id";

    if ($conn->query($update_sql) === TRUE) {
        $_SESSION['airline_msg']      = 'Airline updated successfully!';
        $_SESSION['airline_msg_type'] = 'success';
    } else {
        $_SESSION['airline_msg']      = 'Error updating airline: ' . $conn->error;
        $_SESSION['airline_msg_type'] = 'error';
    }

    header("Location: /flight_booking/view/addAirline.php");
    exit;
}

// ── Fetch all airlines for display ──────────────────────────
$airlines = [];
$result   = $conn->query("SELECT * FROM airlines ORDER BY id DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $airlines[] = $row;
    }
}
?>
