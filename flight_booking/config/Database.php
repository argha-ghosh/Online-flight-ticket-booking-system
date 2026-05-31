<?php
class Database {
    private $host = "sql107.infinityfree.com";
    private $db_name = "if0_42063720_flight_booking";
    private $username = "if0_42063720";
    private $password = "K8CvDhFFpMw0ft";
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