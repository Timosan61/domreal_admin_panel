<?php
// Простая диагностика для LidTracker (только для администраторов)
session_start();
require_once '../auth/session.php';
checkAuth();

// Проверка доступа только для администраторов
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index_new.php');
    exit();
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>LidTracker - Диагностика</h1>";

// Подключение к БД
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    echo "<p style='color: green;'>✅ Подключение к БД: <strong>Успешно</strong></p>";

    // Проверка таблицы leads
    $stmt = $db->query("SELECT COUNT(*) as count FROM leads");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>📊 Всего лидов в базе: <strong>{$result['count']}</strong></p>";

    // Последние 5 лидов
    $stmt = $db->query("
        SELECT
            id,
            source,
            phone,
            name,
            status,
            created_at
        FROM leads
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        echo "<p style='color: orange;'>⚠️ Лиды не найдены</p>";
    } else {
        echo "<h3>Последние 5 лидов:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Источник</th>";
        echo "<th>Телефон</th>";
        echo "<th>Имя</th>";
        echo "<th>Статус</th>";
        echo "<th>Дата создания</th>";
        echo "</tr>";

        foreach ($leads as $lead) {
            echo "<tr>";
            echo "<td>#{$lead['id']}</td>";
            echo "<td>" . htmlspecialchars($lead['source']) . "</td>";
            echo "<td>" . htmlspecialchars($lead['phone'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($lead['name'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($lead['status']) . "</td>";
            echo "<td>" . htmlspecialchars($lead['created_at']) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    // Проверка фильтра "Сегодня"
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM leads
        WHERE DATE(created_at) = CURDATE()
    ");
    $today = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>📅 Лидов за сегодня (CURDATE()): <strong>{$today['count']}</strong></p>";
    echo "<p>🕐 Текущая дата сервера: <strong>" . date('Y-m-d H:i:s') . "</strong></p>";

    // SQL запрос из leads.php (с фильтром "Сегодня")
    $testQuery = "
        SELECT
            id,
            phone,
            name,
            status
        FROM leads
        WHERE DATE(created_at) = CURDATE()
        ORDER BY created_at DESC
        LIMIT 100
    ";

    echo "<h3>Тест SQL запроса (фильтр 'Сегодня'):</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>" . htmlspecialchars($testQuery) . "</pre>";

    $stmt = $db->query($testQuery);
    $testLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Результат: <strong>" . count($testLeads) . " лидов</strong></p>";

    if (!empty($testLeads)) {
        echo "<ul>";
        foreach ($testLeads as $lead) {
            echo "<li>Lead #{$lead['id']}: {$lead['phone']} - {$lead['name']} ({$lead['status']})</li>";
        }
        echo "</ul>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><a href='leads.php'>← Вернуться к списку лидов</a></p>";
echo "<p><small>Эта страница для диагностики. Удалите debug.php после проверки.</small></p>";
?>
