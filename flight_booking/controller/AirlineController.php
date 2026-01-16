<?php
include("../model/db_conn.php");

// Add new airline
if (isset($_POST['submit'])) {
    $airline_name = $_POST['airline_name'];
    $country_name = $_POST['country_name'];
    $airline_code = $_POST['airline_code'];
    $airline_details = $_POST['airline_details'];

    $image_name = $_FILES['image']['name'];
    $image_temp = $_FILES['image']['tmp_name'];
    $image_path = '../view/onload/' . $image_name;  
    move_uploaded_file($image_temp, $image_path);

    $sql = "INSERT INTO airlines (airline_name, country_name, airline_code, airline_details, image) 
            VALUES ('$airline_name', '$country_name', '$airline_code', '$airline_details', '$image_name')";
    if ($conn->query($sql) === TRUE) {
        echo "New airline added successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$airlines = [];
$sql = "SELECT * FROM airlines";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $airlines[] = $row;
    }
}
// Delete Airline Information
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM airlines WHERE id=$delete_id";
    if ($conn->query($delete_sql) === TRUE) {
        header("Location: addAirline.php");
    } else {
        echo "Error: " . $conn->error;
    }
}

// Update Airline Information
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $airline_name = $_POST['airline_name'];
    $country_name = $_POST['country_name'];
    $airline_code = $_POST['airline_code'];
    $airline_details = $_POST['airline_details'];

    $image_name = $_FILES['image']['name'];
    if ($image_name != "") {
        $image_temp = $_FILES['image']['tmp_name'];
        $image_path = 'view/onload/' . $image_name;
        move_uploaded_file($image_temp, $image_path);

        $update_sql = "UPDATE airlines SET airline_name='$airline_name', country_name='$country_name', 
                       airline_code='$airline_code', airline_details='$airline_details', image='$image_path' 
                       WHERE id='$id'";
    } else {
        $update_sql = "UPDATE airlines SET airline_name='$airline_name', country_name='$country_name', 
                       airline_code='$airline_code', airline_details='$airline_details' 
                       WHERE id='$id'";
    }

    if ($conn->query($update_sql) === TRUE) {
        echo "Record updated successfully";
        header("Location: addAirline.php");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

