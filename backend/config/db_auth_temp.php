<?php
// Separate database for temporary authentication data (OTP, verification)

class AuthTempDatabase {
    private string $host = 'localhost';
    private string $db_name = 'iit_shelf_auth_temp';
    private string $db_user = 'root';
    private string $db_pass = '';
    private ?PDO $conn = null;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: $this->host;
        $this->db_name = getenv('DB_AUTH_TEMP_NAME') ?: $this->db_name;
        $this->db_user = getenv('DB_USER') ?: $this->db_user;
        $this->db_pass = getenv('DB_PASS') ?: $this->db_pass;
    }

    public function connect(): PDO {
        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, $this->db_user, $this->db_pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Auth Temp Database Connection Error: ' . $e->getMessage());
        }

        return $this->conn;
    }
}
?>
