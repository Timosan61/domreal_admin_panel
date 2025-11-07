<?php
/**
 * Debug script для диагностики проблемы с пагинацией
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("❌ Не удалось подключиться к базе данных\n");
}

echo "✅ Подключение к базе данных успешно\n\n";

// 1. Проверка размера таблицы
echo "=== 1. Размер таблицы client_enrichment ===\n";
$start = microtime(true);
$stmt = $db->query("SELECT COUNT(*) as total FROM client_enrichment");
$total = $stmt->fetch()['total'];
$elapsed = microtime(true) - $start;
echo "Всего записей: " . number_format($total) . "\n";
echo "Время выполнения: " . round($elapsed * 1000, 2) . " мс\n\n";

// 2. Проверка индексов
echo "=== 2. Индексы на таблице ===\n";
$stmt = $db->query("SHOW INDEX FROM client_enrichment");
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$index_names = array_unique(array_column($indexes, 'Key_name'));
echo "Найдено индексов: " . count($index_names) . "\n";
foreach ($index_names as $idx) {
    echo "  - $idx\n";
}
echo "\n";

// 3. Тест COUNT с фильтрами (симуляция запроса из API)
echo "=== 3. Тест COUNT с типичными фильтрами ===\n";
$test_query = "SELECT COUNT(*) as total FROM client_enrichment WHERE 1=1";
$start = microtime(true);
$stmt = $db->query($test_query);
$count = $stmt->fetch()['total'];
$elapsed = microtime(true) - $start;
echo "Время COUNT без фильтров: " . round($elapsed * 1000, 2) . " мс\n";

// 4. Тест SELECT с LIMIT/OFFSET (страница 10)
echo "\n=== 4. Тест SELECT с OFFSET (страница 10) ===\n";
$page = 10;
$per_page = 50;
$offset = ($page - 1) * $per_page;
$test_query = "SELECT id, client_phone, inn, enrichment_status, created_at
               FROM client_enrichment
               ORDER BY created_at DESC
               LIMIT $per_page OFFSET $offset";
$start = microtime(true);
$stmt = $db->query($test_query);
$records = $stmt->fetchAll();
$elapsed = microtime(true) - $start;
echo "Записей получено: " . count($records) . "\n";
echo "Время выполнения: " . round($elapsed * 1000, 2) . " мс\n";

// 5. Тест с большим OFFSET (страница 50)
echo "\n=== 5. Тест SELECT с большим OFFSET (страница 50) ===\n";
$page = 50;
$offset = ($page - 1) * $per_page;
$test_query = "SELECT id, client_phone, inn, enrichment_status, created_at
               FROM client_enrichment
               ORDER BY created_at DESC
               LIMIT $per_page OFFSET $offset";
$start = microtime(true);
$stmt = $db->query($test_query);
$records = $stmt->fetchAll();
$elapsed = microtime(true) - $start;
echo "Записей получено: " . count($records) . "\n";
echo "Время выполнения: " . round($elapsed * 1000, 2) . " мс\n";

// 6. EXPLAIN для анализа
echo "\n=== 6. EXPLAIN для основного запроса ===\n";
$explain_query = "EXPLAIN SELECT * FROM client_enrichment ORDER BY created_at DESC LIMIT 50 OFFSET 450";
$stmt = $db->query($explain_query);
$explain = $stmt->fetchAll();
foreach ($explain as $row) {
    echo "Type: " . $row['type'] . ", Rows: " . $row['rows'] . ", Extra: " . $row['Extra'] . "\n";
}

echo "\n✅ Диагностика завершена\n";
?>
