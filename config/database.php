<?php
/**
 * Database Configuration
 * Подключение к базе данных calls_db
 */

class Database {
    // Локальное подключение (основное) - localhost на этом сервере
    private $host = "localhost";
    private $port = "3306";
    private $db_name = "calls_db";
    private $username = "datalens_user";
    private $password = "datalens_readonly_2024";

    // Удаленное подключение (fallback) - для Docker используем IP хоста
    private $remote_host = "172.17.0.1";
    private $remote_port = "3306";

    public $conn;

    /**
     * Получить соединение с базой данных
     */
    public function getConnection() {
        $this->conn = null;

        try {
            // Пробуем подключиться к локальной БД (через host.docker.internal из контейнера)
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch(PDOException $e) {
            // Если не получилось, пробуем удаленное подключение
            try {
                $dsn = "mysql:host=" . $this->remote_host . ";port=" . $this->remote_port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            } catch(PDOException $remote_e) {
                error_log("Local connection error: " . $e->getMessage());
                error_log("Remote connection error: " . $remote_e->getMessage());
            }
        }

        return $this->conn;
    }
}
