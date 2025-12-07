<?php
/**
 * Тестовая страница для проверки работоспособности
 */
echo "✅ PHP работает!<br>";
echo "✅ Версия PHP: " . phpversion() . "<br>";

// Проверка подключения к БД
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo "❌ База данных: НЕ ПОДКЛЮЧЕНА<br>";
} else {
    echo "✅ База данных: ПОДКЛЮЧЕНА<br>";

    // Проверяем количество пользователей
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Пользователей в БД: " . $result['count'] . "<br>";
}

echo "<br>Время: " . date('Y-m-d H:i:s') . "<br>";
