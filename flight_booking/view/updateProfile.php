<?php
include("../model/db_conn.php");

$data = json_decode(file_get_contents("php://input"), true);

$name = $data['name'];
$age = $data['age'];
$dob = $data['dob'];
$address = $data['address'];
$phone = $data['phone'];
$gender = $data['gender'];
$email = $data['email'];


$admin_id = 2;

$stmt = $conn->prepare("UPDATE admininfo SET name = ?, age = ?, dob = ?, address = ?, phone = ?, gender = ?, email = ?,  WHERE id = ?");
$stmt->bind_param("sissssssi", $name, $age, $dob, $address, $phone, $gender, $email, $admin_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error']);
}
?>

