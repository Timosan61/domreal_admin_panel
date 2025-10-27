<?php
/**
 * Применение миграции 006: Добавление всех полей от поставщиков лидов
 */

require_once 'config/database.php';

echo "=== Migration 006: Добавление полей от поставщиков ===\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        die("❌ Не удалось подключиться к БД\n");
    }

    echo "✓ Подключение к БД установлено\n";

    // Проверяем текущие колонки
    $stmt = $db->query("SHOW COLUMNS FROM leads");
    $existingColumns = [];
    while ($row = $stmt->fetch()) {
        $existingColumns[] = $row['Field'];
    }

    echo "✓ Текущее количество колонок: " . count($existingColumns) . "\n\n";

    // Список новых колонок для добавления
    $newColumns = [
        "site_name VARCHAR(255) NULL COMMENT 'Название сайта (Creatium, GCK)' AFTER page_url",
        "site_url VARCHAR(500) NULL COMMENT 'URL сайта (Creatium, GCK)' AFTER site_name",
        "page_name VARCHAR(255) NULL COMMENT 'Название страницы (Creatium)' AFTER site_url",
        "form_name VARCHAR(255) NULL COMMENT 'Название формы (Creatium)' AFTER page_name",
        "browser VARCHAR(100) NULL COMMENT 'Браузер посетителя (GCK)' AFTER user_agent",
        "device VARCHAR(100) NULL COMMENT 'Устройство: Desktop/Mobile/Tablet (GCK)' AFTER browser",
        "platform VARCHAR(100) NULL COMMENT 'Операционная система (GCK)' AFTER device",
        "country VARCHAR(100) NULL COMMENT 'Страна (GCK)' AFTER geolocation",
        "region VARCHAR(100) NULL COMMENT 'Регион (GCK)' AFTER country",
        "city VARCHAR(100) NULL COMMENT 'Город (GCK)' AFTER region",
        "roistat_visit VARCHAR(100) NULL COMMENT 'Roistat ID визита (GCK, Marquiz)' AFTER utm_term",
        "client_comment TEXT NULL COMMENT 'Комментарий/ID клиента в РК (GCK)' AFTER roistat_visit",
        "quiz_id VARCHAR(100) NULL COMMENT 'ID квиза (Marquiz)' AFTER external_id",
        "quiz_name VARCHAR(255) NULL COMMENT 'Название квиза (Marquiz)' AFTER quiz_id",
        "quiz_answers JSON NULL COMMENT 'Ответы на вопросы квиза (Marquiz)' AFTER quiz_name",
        "quiz_result JSON NULL COMMENT 'Результат квиза (Marquiz)' AFTER quiz_answers",
        "ab_test VARCHAR(50) NULL COMMENT 'AB-тест: A или B (Marquiz)' AFTER quiz_result",
        "timezone INT NULL COMMENT 'Часовой пояс пользователя (Marquiz)' AFTER ab_test",
        "lang VARCHAR(10) NULL COMMENT 'Язык интерфейса: ru, en (Marquiz)' AFTER timezone",
        "cookies JSON NULL COMMENT 'Cookies пользователя (Marquiz)' AFTER lang",
        "discount DECIMAL(10,2) NULL COMMENT 'Скидка (Marquiz)' AFTER cookies",
        "discount_type VARCHAR(50) NULL COMMENT 'Тип скидки (Marquiz)' AFTER discount",
    ];

    echo "Добавление колонок:\n";
    $added = 0;
    $skipped = 0;

    foreach ($newColumns as $columnDef) {
        // Извлекаем имя колонки
        preg_match('/^(\w+)\s/', $columnDef, $matches);
        $columnName = $matches[1];

        if (in_array($columnName, $existingColumns)) {
            echo "  ⊘ $columnName - уже существует\n";
            $skipped++;
            continue;
        }

        try {
            $db->exec("ALTER TABLE leads ADD COLUMN $columnDef");
            echo "  ✓ $columnName - добавлена\n";
            $added++;
        } catch (PDOException $e) {
            echo "  ✗ $columnName - ошибка: " . $e->getMessage() . "\n";
        }
    }

    echo "\n";

    // Добавление индексов
    echo "Добавление индексов:\n";
    $indexes = [
        "idx_site_name" => "site_name",
        "idx_form_name" => "form_name",
        "idx_browser" => "browser",
        "idx_device" => "device",
        "idx_platform" => "platform",
        "idx_country" => "country",
        "idx_city" => "city",
        "idx_quiz_name" => "quiz_name",
        "idx_roistat_visit" => "roistat_visit",
    ];

    foreach ($indexes as $indexName => $columnName) {
        try {
            $db->exec("ALTER TABLE leads ADD INDEX $indexName ($columnName)");
            echo "  ✓ $indexName\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "  ⊘ $indexName - уже существует\n";
            } else {
                echo "  ✗ $indexName - ошибка: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n";

    // Проверка результата
    $stmt = $db->query("SHOW COLUMNS FROM leads");
    $finalColumns = [];
    while ($row = $stmt->fetch()) {
        $finalColumns[] = $row['Field'];
    }

    echo "=== Результат ===\n";
    echo "Колонок до миграции: " . count($existingColumns) . "\n";
    echo "Колонок после миграции: " . count($finalColumns) . "\n";
    echo "Добавлено колонок: $added\n";
    echo "Пропущено (уже существовали): $skipped\n";
    echo "\n✅ Миграция успешно применена!\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
