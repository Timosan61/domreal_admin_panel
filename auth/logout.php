<?php
/**
 * Выход из системы
 */
session_start();

// Удаляем запись сессии из БД
if (isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    if ($db !== null) {
        $session_id = session_id();
        $query = "DELETE FROM sessions WHERE session_id = :session_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':session_id', $session_id);
        $stmt->execute();
    }
}

// Очищаем все данные сессии
$_SESSION = array();

// Удаляем cookie сессии
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Уничтожаем сессию
session_destroy();

// Редирект на страницу входа
header('Location: login.php');
exit();
