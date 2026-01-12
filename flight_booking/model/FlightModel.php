<?php
require_once "../config/Database.php";

class FlightModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();

        if (!$this->conn) { throw new Exception('Database connection failed'); } 
    }

    public function addFlight($data) {
        $sql = "INSERT INTO flights 
                (flight_name, airline_name, flight_code, departure, arrival, duration, price, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "ssssssds",
            $data['flight_name'],
            $data['airline_name'],
            $data['flight_code'],
            $data['departure'],
            $data['arrival'],
            $data['duration'],
            $data['price'],
            $data['image']
        );

        return $stmt->execute();
    }

    public function getAllFlights() {
        $sql = "SELECT * FROM flights";
        return $this->conn->query($sql);
    }

    // Update and delete methods can be added here as needed

    public function getFlightById($id) {
    $sql = "SELECT * FROM flights WHERE id = ?";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

public function updateFlight($data) {
    $sql = "UPDATE flights SET
            flight_name = ?, airline_name = ?, flight_code = ?,
            departure = ?, arrival = ?, duration = ?, price = ?, image = ?
            WHERE id = ?";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param(
        "ssssssdsi",
        $data['flight_name'],
        $data['airline_name'],
        $data['flight_code'],
        $data['departure'],
        $data['arrival'],
        $data['duration'],
        $data['price'],
        $data['image'],
        $data['id']
    );

    return $stmt->execute();
}

public function deleteFlight($id) {
    $sql = "DELETE FROM flights WHERE id = ?";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

}
?>
