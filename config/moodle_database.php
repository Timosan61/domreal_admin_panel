<?php
/**
 * Класс для подключения к базе данных Moodle
 *
 * Используется для прямых SQL запросов к Moodle БД
 * для получения данных о студентах, прогрессе, content blocks и т.д.
 *
 * @author Claude Code
 * @date 2025-12-11
 */
class MoodleDatabase {
    private $host = "localhost";
    private $port = "3306";
    private $db_name = "moodle_db";
    private $username = "moodle_user";
    private $password = "Moodle2025!Strong";
    private $remote_host = "172.17.0.1"; // Docker fallback
    public $conn;

    /**
     * Получить PDO подключение к Moodle БД
     *
     * Пытается подключиться к localhost, при неудаче - к Docker host
     *
     * @return PDO|null Возвращает PDO connection или null при ошибке
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Попытка подключиться к Docker host
            try {
                $dsn = "mysql:host=" . $this->remote_host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                error_log("Moodle Database Connection Error: " . $e->getMessage());
                return null;
            }
        }

        return $this->conn;
    }

    /**
     * Закрыть подключение к БД
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
