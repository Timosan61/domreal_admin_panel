<?php
// Тестовый файл для диагностики manager_risks.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Диагностика manager_risks.php</h1>";

// Тест 1: Сессия
echo "<h2>1. Проверка сессии</h2>";
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";

// Тест 2: Подключение к БД
echo "<h2>2. Проверка подключения к БД</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "✅ Подключение к БД успешно<br>";

        // Проверяем таблицы
        echo "<h3>Проверка таблиц:</h3>";

        $tables = ['calls_raw', 'crm_alert_flags', 'sessions', 'users'];
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            echo ($exists ? "✅" : "❌") . " $table<br>";
        }
    } else {
        echo "❌ Ошибка подключения к БД<br>";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

// Тест 3: Проверка auth/session.php
echo "<h2>3. Проверка auth/session.php</h2>";
try {
    if (file_exists('auth/session.php')) {
        echo "✅ Файл auth/session.php существует<br>";
        require_once 'auth/session.php';
        echo "✅ Файл auth/session.php загружен<br>";
    } else {
        echo "❌ Файл auth/session.php не найден<br>";
    }
} catch (Exception $e) {
    echo "❌ Ошибка загрузки auth/session.php: " . $e->getMessage() . "<br>";
}

// Тест 4: Проверка includes/sidebar.php
echo "<h2>4. Проверка includes/sidebar.php</h2>";
if (file_exists('includes/sidebar.php')) {
    echo "✅ Файл includes/sidebar.php существует<br>";
    $size = filesize('includes/sidebar.php');
    echo "Размер: $size байт<br>";
} else {
    echo "❌ Файл includes/sidebar.php не найден<br>";
}

// Тест 5: Проверка assets
echo "<h2>5. Проверка assets</h2>";
$assets = [
    'assets/css/style.css',
    'assets/js/sidebar.js',
    'assets/js/theme-switcher.js'
];
foreach ($assets as $asset) {
    $exists = file_exists($asset);
    echo ($exists ? "✅" : "❌") . " $asset<br>";
}

echo "<h2>✅ Диагностика завершена</h2>";
