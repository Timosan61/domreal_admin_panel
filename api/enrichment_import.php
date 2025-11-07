<?php
/**
 * API для импорта телефонных номеров в базу данных
 * POST /api/enrichment_import.php
 *
 * ВАЖНО: Доступ только для администраторов!
 *
 * Параметры POST:
 * - phones (string) - список номеров (через запятую, точку с запятой или новую строку)
 *
 * Ответ JSON:
 * {
 *   "success": true,
 *   "added": 10,
 *   "duplicates": 2,
 *   "invalid": 1,
 *   "phones": [...],
 *   "errors": [...]
 * }
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';

// КРИТИЧЕСКИ ВАЖНО: Проверка админских прав (API режим)
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

// Получаем данные из POST
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['phones']) || empty($input['phones'])) {
    http_response_code(400);
    echo json_encode(["error" => "Параметр 'phones' обязателен"]);
    exit();
}

$phones_text = $input['phones'];

/**
 * Парсинг телефонных номеров из текста
 */
function parsePhones($text) {
    // Разделяем по переносам строк, запятым, точкам с запятой, пробелам
    $raw_phones = preg_split('/[\n\r,;\s]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $parsed = [];
    foreach ($raw_phones as $phone) {
        // Удаляем все символы кроме цифр и +
        $phone = trim($phone);
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Удаляем + в начале
        $cleaned = ltrim($cleaned, '+');

        // Удаляем 8 или 7 в начале
        if (strlen($cleaned) === 11) {
            if ($cleaned[0] === '8' || $cleaned[0] === '7') {
                $cleaned = substr($cleaned, 1);
            }
        }

        // Проверяем, что осталось ровно 10 цифр и начинается с 9
        if (strlen($cleaned) === 10 && $cleaned[0] === '9') {
            $parsed[] = $cleaned;
        }
    }

    return array_unique($parsed);
}

// Парсим телефоны
$phones = parsePhones($phones_text);

if (empty($phones)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Не найдено валидных телефонных номеров",
        "hint" => "Формат: +79001234567, 89001234567 или 9001234567"
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Счетчики
$added = 0;
$duplicates = 0;
$errors = [];
$added_phones = [];

// Обрабатываем каждый номер
foreach ($phones as $phone) {
    // Формируем полный номер с +7
    $full_phone = '+7' . $phone;

    try {
        // Проверяем, существует ли уже такой номер
        $check_query = "SELECT id FROM client_enrichment WHERE client_phone = :phone LIMIT 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':phone', $full_phone);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            $duplicates++;
            continue;
        }

        // Вставляем новый номер
        $insert_query = "INSERT INTO client_enrichment (
            client_phone,
            enrichment_status,
            created_at,
            updated_at
        ) VALUES (
            :phone,
            'pending',
            NOW(),
            NOW()
        )";

        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':phone', $full_phone);

        if ($insert_stmt->execute()) {
            $added++;
            $added_phones[] = $full_phone;
        } else {
            $errors[] = "Не удалось добавить $full_phone: " . implode(', ', $insert_stmt->errorInfo());
        }
    } catch (PDOException $e) {
        $errors[] = "Ошибка для $full_phone: " . $e->getMessage();
    }
}

// Возвращаем результат
$response = [
    "success" => true,
    "added" => $added,
    "duplicates" => $duplicates,
    "invalid" => count($phones) - $added - $duplicates,
    "total_parsed" => count($phones),
    "phones" => $added_phones,
];

if (!empty($errors)) {
    $response["errors"] = $errors;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
