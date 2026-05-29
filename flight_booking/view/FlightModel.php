<?php
require_once __DIR__ . "/../config/Database.php";

class FlightModel {
    private $conn;
    private $lastError = '';

    public function __construct() {
        $database   = new Database();
        $this->conn = $database->connect();
        if (!$this->conn) { throw new Exception('Database connection failed'); }
    }

    public function getLastError() { return $this->lastError; }

    // Columns: flight_name, airline_name, flight_code, departure, arrival,
    //          departure_time, arrival_time, duration, price,
    //          flight_class, seat_class, total_seats, seat, discount_pct, status, image
    // Types:   s              s             s            s          s
    //          s              s             s            d
    //          s              s             i             i    d              s       s
    // = "ssssssssdssiiidss" — 17 params, 17 type chars

    public function addFlight($data) {
        $sql = "INSERT INTO flights
                    (flight_name, airline_name, flight_code, departure, arrival,
                     departure_time, arrival_time, duration, price,
                     flight_class, seat_class, total_seats, seat, discount_pct, status, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->lastError = 'Prepare failed: ' . $this->conn->error;
            return false;
        }

        // 16 params: s s s s s  s  s  s  d  s  s  i  i  d  s  s
        $stmt->bind_param(
            "ssssssssdssiiidss",
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
            $data['seat_class'],
            $data['total_seats'],
            $data['seat'],
            $data['discount_pct'],
            $data['status'],
            $data['image']
        );

        $result = $stmt->execute();
        if (!$result) { $this->lastError = $stmt->error; }
        $stmt->close();
        return $result;
    }

    public function getAllFlights() {
        return $this->conn->query("SELECT * FROM flights ORDER BY id DESC");
    }

    public function getFlightById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM flights WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function updateFlight($data) {
        $sql = "UPDATE flights SET
                    flight_name=?, airline_name=?, flight_code=?,
                    departure=?, arrival=?, departure_time=?, arrival_time=?,
                    duration=?, price=?, flight_class=?, seat_class=?,
                    total_seats=?, seat=?, discount_pct=?, status=?, image=?
                WHERE id=?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->lastError = 'Prepare failed: ' . $this->conn->error;
            return false;
        }

        // 17 params: s s s s s  s  s  s  d  s  s  i  i  d  s  s  i
        $stmt->bind_param(
            "ssssssssdssiiidssi",
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
            $data['seat_class'],
            $data['total_seats'],
            $data['seat'],
            $data['discount_pct'],
            $data['status'],
            $data['image'],
            $data['id']
        );

        $result = $stmt->execute();
        if (!$result) { $this->lastError = $stmt->error; }
        $stmt->close();
        return $result;
    }

    public function deleteFlight($id) {
        $stmt = $this->conn->prepare("DELETE FROM flights WHERE id = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        if (!$result) { $this->lastError = $stmt->error; }
        $stmt->close();
        return $result;
    }
}
?>
