<?php
/**
 * Database connection helper для webhook receiver
 * Подключение к БД calls_db через MySQLi
 */

function get_db_connection() {
    $host = 'localhost';
    $db = 'calls_db';
    $user = 'calls_user';
    $pass = 'calls_password_2023';

    try {
        $conn = new mysqli($host, $user, $pass, $db);

        // Проверка ошибок подключения
        if ($conn->connect_error) {
            error_log("DB Connection Error: " . $conn->connect_error);
            return null;
        }

        // Установка charset
        $conn->set_charset('utf8mb4');

        return $conn;
    } catch (Exception $e) {
        error_log("DB Connection Error: " . $e->getMessage());
        return null;
    }
}
