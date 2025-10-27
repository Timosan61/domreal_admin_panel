<?php
/**
 * Тест валидации и парсинга Creatium form-urlencoded
 */

echo "=== ТЕСТ CREATIUM FORM-URLENCODED ===\n\n";

// Реальный payload из логов (14:45:11)
$realPayload = "visit[user_agent]=Mozilla/5.0&visit[ip]=176.59.46.33&visit[geolocation]=Россия&site[name]=Марина-Гарден&site[url]=https://marinagardensochi.ru&page[name]=Главная&order[form_name]=БЛОК инвестирование&order[fields_by_name][Имя]=Лена&order[fields_by_name][Номер телефона]=+7 (904) 421-45-85";

echo "1️⃣  РЕАЛЬНЫЙ PAYLOAD (из логов 14:45:11):\n";
echo substr($realPayload, 0, 200) . "...\n\n";

// Парсинг
parse_str($realPayload, $data);

echo "2️⃣  РАСПАРСЕННЫЕ ДАННЫЕ:\n";
echo "Site name: " . ($data['site']['name'] ?? 'NULL') . "\n";
echo "Page name: " . ($data['page']['name'] ?? 'NULL') . "\n";
echo "Form name: " . ($data['order']['form_name'] ?? 'NULL') . "\n";
echo "Имя: " . ($data['order']['fields_by_name']['Имя'] ?? 'NULL') . "\n";
echo "Телефон: " . ($data['order']['fields_by_name']['Номер телефона'] ?? 'NULL') . "\n\n";

// Проверка извлечения телефона
$phone = $data['order']['fields_by_name']['Номер телефона'] ?? null;

echo "3️⃣  ИЗВЛЕЧЁННЫЙ ТЕЛЕФОН:\n";
echo "Raw: '$phone'\n";

// Очистка
$cleaned = preg_replace('/\D/', '', $phone);
echo "Cleaned: '$cleaned'\n";
echo "Length: " . strlen($cleaned) . " цифр\n\n";

// Валидация (как в WebhookReceiver)
echo "4️⃣  ВАЛИДАЦИЯ:\n";

if (strlen($cleaned) === 11 && $cleaned[0] === '7') {
    echo "✅ Формат 1: +7XXXXXXXXXX (11 цифр, начинается с 7)\n";
    echo "Normalized: +" . $cleaned . "\n";
} elseif (strlen($cleaned) === 10 && $cleaned[0] === '9') {
    echo "✅ Формат 2: 9XXXXXXXXX (10 цифр, начинается с 9)\n";
    echo "Normalized: +7" . $cleaned . "\n";
} elseif (strlen($cleaned) === 11) {
    echo "✅ Формат 3 (ТЕСТОВЫЙ): Любые 11 цифр\n";
    echo "Normalized: +" . $cleaned . "\n";
} else {
    echo "❌ ОШИБКА: Не подходит ни под один формат\n";
    echo "Ожидается: +79XXXXXXXXX или 11 цифр\n";
}

echo "\n";

// Проверка JSON encoding для сохранения в БД
echo "5️⃣  JSON ENCODING (для raw_payload):\n";
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    echo "❌ json_encode() FAILED: " . json_last_error_msg() . "\n";
} else {
    echo "✅ JSON валиден\n";
    echo "Размер: " . strlen($json) . " байт\n";
    echo "Первые 100 символов:\n";
    echo substr($json, 0, 100) . "...\n";
}

echo "\n";

// Тестовый payload (как приходит от панели Creatium)
echo "6️⃣  ТЕСТОВЫЙ PAYLOAD (тестовая кнопка в Creatium):\n\n";

$testPayload = "visit[ip]=1.2.3.4&site[name]=Тестовый сайт&order[fields_by_name][Имя]=Тест&order[fields_by_name][Номер телефона]=+7 (999) 123-45-67";
parse_str($testPayload, $testData);

echo "Имя: " . ($testData['order']['fields_by_name']['Имя'] ?? 'NULL') . "\n";
echo "Телефон (raw): " . ($testData['order']['fields_by_name']['Номер телефона'] ?? 'NULL') . "\n";

$testPhone = $testData['order']['fields_by_name']['Номер телефона'] ?? null;
$testCleaned = preg_replace('/\D/', '', $testPhone);
echo "Телефон (cleaned): $testCleaned\n";
echo "Длина: " . strlen($testCleaned) . " цифр\n";

if (strlen($testCleaned) === 11 && $testCleaned[0] === '7') {
    echo "✅ ПРОЙДЁТ валидацию → +$testCleaned\n";
} else {
    echo "❌ НЕ ПРОЙДЁТ валидацию\n";
}
