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
                    (flight_name, airline_name, flight_code, departure, arrival,
                     departure_time, arrival_time, duration, price,
                     flight_class, total_seats, status, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssdssss",
            $data['flight_name'],
            $data['airline_name'],
            $data['flight_code'],
            $data['departure'],
            $data['arrival'],
            $data['departure_time'],
            $data['arrival_time'],
            $data['duration'],
            $data['price'],
            $data['flight_class'],
            $data['total_seats'],
            $data['status'],
            $data['image']
        );
        return $stmt->execute();
    }

    public function getAllFlights() {
        return $this->conn->query("SELECT * FROM flights ORDER BY id DESC");
    }

    public function getFlightById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM flights WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateFlight($data) {
        $sql = "UPDATE flights SET
                    flight_name=?, airline_name=?, flight_code=?,
                    departure=?, arrival=?, departure_time=?, arrival_time=?,
                    duration=?, price=?, flight_class=?, total_seats=?, status=?, image=?
                WHERE id=?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssdssssi",
            $data['flight_name'],
            $data['airline_name'],
            $data['flight_code'],
            $data['departure'],
            $data['arrival'],
            $data['departure_time'],
            $data['arrival_time'],
            $data['duration'],
            $data['price'],
            $data['flight_class'],
            $data['total_seats'],
            $data['status'],
            $data['image'],
            $data['id']
        );
        return $stmt->execute();
    }

    public function deleteFlight($id) {
        $stmt = $this->conn->prepare("DELETE FROM flights WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
