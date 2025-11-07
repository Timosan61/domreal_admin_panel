<?php
/**
 * Тест парсинга form-urlencoded данных от Creatium
 */

// Реальный payload из логов (сокращенная версия)
$rawPayload = "visit[user_agent]=Mozilla/5.0&visit[ip]=176.59.46.33&visit[geolocation]=Россия&site[name]=Марина-Гарден&site[url]=https://marinagardensochi.ru&page[name]=Главная&order[form_name]=БЛОК инвестирование&order[fields_by_name][Имя]=Лена&order[fields_by_name][Номер телефона]=%2B7 (904) 421-45-85";

echo "=== Тест парсинга form-urlencoded ===\n\n";

// Парсинг
parse_str($rawPayload, $data);

echo "1. Результат parse_str():\n";
print_r($data);
echo "\n";

// Проверка json_encode
$json = json_encode($data, JSON_UNESCAPED_UNICODE);

if ($json === false) {
    echo "2. ❌ json_encode() FAILED!\n";
    echo "   Error: " . json_last_error_msg() . "\n";
} else {
    echo "2. ✓ json_encode() успешно\n";
    echo "   Длина JSON: " . strlen($json) . " байт\n";
    echo "   Первые 200 символов:\n";
    echo substr($json, 0, 200) . "...\n";
}

echo "\n3. Проверка валидности JSON:\n";
if ($json !== false) {
    $decoded = json_decode($json, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "   ❌ Декодирование не удалось: " . json_last_error_msg() . "\n";
    } else {
        echo "   ✓ JSON валиден\n";
    }
}

echo "\n4. Проверка сохранения в MySQL:\n";
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("
        INSERT INTO leads (source, raw_payload, phone_raw, created_at)
        VALUES (:source, :raw_payload, :phone_raw, NOW())
    ");

    $result = $stmt->execute([
        'source' => 'creatium',
        'raw_payload' => $json,
        'phone_raw' => '+79999999999'
    ]);

    if ($result) {
        $leadId = $db->lastInsertId();
        echo "   ✓ Успешно сохранено в БД (ID: $leadId)\n";

        // Удаляем тестовый лид
        $db->exec("DELETE FROM leads WHERE id = $leadId");
        echo "   ✓ Тестовый лид удален\n";
    }
} catch (PDOException $e) {
    echo "   ❌ Ошибка MySQL: " . $e->getMessage() . "\n";
}
