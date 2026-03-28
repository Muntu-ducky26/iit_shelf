<?php
class PreRegistrationDatabase {
    private $host = 'localhost';
    private $db_name = 'iit_shelf_prereg';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function __construct() {
        // Keep credentials aligned with local XAMPP defaults while allowing env overrides.
        $this->host = getenv('DB_HOST') ?: $this->host;
        $this->username = getenv('DB_USER') ?: $this->username;
        $this->password = getenv('DB_PASS') ?: $this->password;
        $this->db_name = getenv('DB_PREREG_NAME') ?: $this->db_name;
    }

    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log('PreReg DB Connection Error: ' . $e->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}
?>
