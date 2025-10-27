<?php
/**
 * Проверка статуса миграции 006
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        die("❌ Не удалось подключиться к БД\n");
    }

    echo "=== Проверка статуса миграции 006 ===\n\n";

    // 1. Проверяем количество колонок
    $stmt = $db->query("SHOW COLUMNS FROM leads");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 Всего колонок в таблице leads: " . count($columns) . "\n\n";

    // 2. Проверяем новые колонки
    $newColumns = ['site_name', 'site_url', 'page_name', 'form_name',
                   'browser', 'device', 'platform', 'country', 'region', 'city',
                   'roistat_visit', 'client_comment', 'quiz_id', 'quiz_name',
                   'quiz_answers', 'quiz_result', 'ab_test', 'timezone', 'lang',
                   'cookies', 'discount', 'discount_type'];

    echo "🔍 Проверка новых колонок (Migration 006):\n";
    $existingColumns = array_column($columns, 'Field');
    $found = 0;
    $missing = 0;

    foreach ($newColumns as $col) {
        if (in_array($col, $existingColumns)) {
            echo "  ✓ $col - существует\n";
            $found++;
        } else {
            echo "  ✗ $col - НЕ НАЙДЕНА\n";
            $missing++;
        }
    }

    echo "\n";
    echo "Найдено: $found / " . count($newColumns) . "\n";
    echo "Отсутствует: $missing\n\n";

    // 3. Проверяем есть ли данные в новых полях
    $stmt = $db->query("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN site_name IS NOT NULL THEN 1 ELSE 0 END) as with_site_name,
               SUM(CASE WHEN browser IS NOT NULL THEN 1 ELSE 0 END) as with_browser,
               SUM(CASE WHEN quiz_name IS NOT NULL THEN 1 ELSE 0 END) as with_quiz_name
        FROM leads
    ");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "📈 Статистика данных:\n";
    echo "  Всего лидов: " . $data['total'] . "\n";
    echo "  С site_name: " . $data['with_site_name'] . "\n";
    echo "  С browser: " . $data['with_browser'] . "\n";
    echo "  С quiz_name: " . $data['with_quiz_name'] . "\n\n";

    // 4. Проверяем последние 3 лида
    $stmt = $db->query("
        SELECT id, created_at, source, phone,
               site_name, browser, quiz_name
        FROM leads
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $recentLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📋 Последние 3 лида:\n";
    foreach ($recentLeads as $lead) {
        echo "  ID: " . $lead['id'] . " | " . $lead['created_at'] . " | " . $lead['source'] . "\n";
        echo "    site_name: " . ($lead['site_name'] ?? 'NULL') . "\n";
        echo "    browser: " . ($lead['browser'] ?? 'NULL') . "\n";
        echo "    quiz_name: " . ($lead['quiz_name'] ?? 'NULL') . "\n";
        echo "\n";
    }

    echo "✅ Проверка завершена!\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
