<?php
class Database {
    private $host = "localhost";
    private $db_name = "flight_booking";
    private $username = "root";
    private $password = "";
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new mysqli(
                $this->host,
                $this->username,
                $this->password,
                $this->db_name
            );
        } catch (Exception $e) {
            die("Database Connection Error");
        }

        return $this->conn;
    }
}
?>