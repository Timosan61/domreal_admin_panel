<?php
/**
 * Тест API для проверки агрегированных данных
 */

include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("❌ Не удалось подключиться к БД\n");
}

echo "✅ Подключение к БД успешно\n\n";

// Тестируем запрос с агрегацией
$query = "SELECT
    cr.callid,
    cr.client_phone,
    ar.summary_text,
    ce.aggregate_summary,
    ce.total_calls_count,
    ce.successful_calls_count,
    ce.last_call_date
FROM calls_raw cr
LEFT JOIN analysis_results ar ON cr.callid = ar.callid
LEFT JOIN client_enrichment ce ON cr.client_phone = ce.client_phone
WHERE ce.aggregate_summary IS NOT NULL
LIMIT 3";

$stmt = $db->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll();

echo "📊 Найдено записей с агрегацией: " . count($results) . "\n\n";

foreach ($results as $row) {
    echo "CallID: {$row['callid']}\n";
    echo "  Телефон: {$row['client_phone']}\n";
    echo "  Количество звонков: {$row['total_calls_count']}\n";
    echo "  Успешных: {$row['successful_calls_count']}\n";
    echo "  Агрегация: " . mb_substr($row['aggregate_summary'], 0, 80) . "...\n";
    echo "\n";
}

// Проверяем что поля не NULL
$has_data = false;
foreach ($results as $row) {
    if ($row['aggregate_summary'] !== null && $row['total_calls_count'] > 0) {
        $has_data = true;
        break;
    }
}

if ($has_data) {
    echo "✅ ТЕСТ ПРОЙДЕН: Агрегированные данные корректно возвращаются из БД\n";
} else {
    echo "❌ ТЕСТ НЕ ПРОЙДЕН: Нет агрегированных данных\n";
}
?>
