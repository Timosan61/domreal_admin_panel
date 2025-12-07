<?php
/**
 * API для создания новой загрузки (batch) импорта телефонов
 * POST /api/enrichment_batch_create.php
 *
 * ВАЖНО: Доступ только для администраторов!
 *
 * Параметры POST:
 * - batch_name (string) - название загрузки (обязательно)
 * - phones (string) - список номеров (через запятую, точку с запятой или новую строку)
 *
 * Ответ JSON:
 * {
 *   "success": true,
 *   "batch_id": 123,
 *   "batch_name": "Клиенты Москва январь 2025",
 *   "added": 50,
 *   "duplicates": 5,
 *   "total_records": 50
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

if (!isset($input['batch_name']) || empty(trim($input['batch_name']))) {
    http_response_code(400);
    echo json_encode(["error" => "Параметр 'batch_name' обязателен"]);
    exit();
}

if (!isset($input['phones']) || empty($input['phones'])) {
    http_response_code(400);
    echo json_encode(["error" => "Параметр 'phones' обязателен"]);
    exit();
}

$batch_name = trim($input['batch_name']);
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

try {
    // Начинаем транзакцию
    $db->beginTransaction();

    // 1. Создаем batch
    $created_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;

    $batch_query = "INSERT INTO enrichment_batches (
        batch_name,
        created_by,
        total_records,
        status
    ) VALUES (
        :batch_name,
        :created_by,
        :total_records,
        'processing'
    )";

    $batch_stmt = $db->prepare($batch_query);
    $batch_stmt->bindParam(':batch_name', $batch_name);
    $batch_stmt->bindParam(':created_by', $created_by);
    $total_records_placeholder = 0; // Временно, обновим после импорта
    $batch_stmt->bindParam(':total_records', $total_records_placeholder);

    if (!$batch_stmt->execute()) {
        throw new Exception("Не удалось создать batch: " . implode(', ', $batch_stmt->errorInfo()));
    }

    $batch_id = $db->lastInsertId();

    // 2. Импортируем номера
    $added = 0;
    $duplicates = 0;
    $errors = [];
    $added_phones = [];

    foreach ($phones as $phone) {
        // Формируем полный номер с +7
        $full_phone = '+7' . $phone;

        // Проверяем, существует ли уже такой номер
        $check_query = "SELECT id FROM client_enrichment WHERE client_phone = :phone LIMIT 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':phone', $full_phone);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            $duplicates++;
            continue;
        }

        // Вставляем новый номер с batch_id
        $insert_query = "INSERT INTO client_enrichment (
            batch_id,
            client_phone,
            enrichment_status,
            created_at,
            updated_at
        ) VALUES (
            :batch_id,
            :phone,
            'pending',
            NOW(),
            NOW()
        )";

        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':batch_id', $batch_id);
        $insert_stmt->bindParam(':phone', $full_phone);

        if ($insert_stmt->execute()) {
            $added++;
            $added_phones[] = $full_phone;
        } else {
            $errors[] = "Не удалось добавить $full_phone: " . implode(', ', $insert_stmt->errorInfo());
        }
    }

    // 3. Обновляем счетчики batch
    $update_batch_query = "UPDATE enrichment_batches
                          SET total_records = :total,
                              pending_records = :total
                          WHERE id = :batch_id";

    $update_batch_stmt = $db->prepare($update_batch_query);
    $update_batch_stmt->bindParam(':total', $added);
    $update_batch_stmt->bindParam(':batch_id', $batch_id);
    $update_batch_stmt->execute();

    // Commit транзакции
    $db->commit();

    // Триггер немедленной обработки воркером
    if ($added > 0) {
        // Отправляем SIGUSR1 сигнал воркеру для немедленной обработки
        $pid_file = '/tmp/enrichment_worker.pid';
        if (file_exists($pid_file)) {
            $pid = trim(file_get_contents($pid_file));
            if ($pid && is_numeric($pid)) {
                // Проверяем, что процесс существует
                $check = shell_exec("ps -p $pid -o comm=");
                if (strpos($check, 'python') !== false) {
                    // Отправляем сигнал для немедленного пробуждения
                    shell_exec("kill -USR1 $pid 2>/dev/null");
                    error_log("[Batch Create] Sent wake-up signal to worker PID $pid");
                }
            }
        }

        // Альтернативный способ: просто запускаем разовую обработку в фоне
        $python_path = '/home/z/ailoca/.cache/pypoetry/virtualenvs/domreal-whisper-rKzriwEo-py3.11/bin/python';
        $worker_path = '/home/z/ailoca/Domreal_Whisper/workers/worker_enrichment.py';

        if (file_exists($python_path) && file_exists($worker_path)) {
            // Запускаем однократную обработку в фоне
            $cmd = "cd /home/z/ailoca/Domreal_Whisper && nohup $python_path -c \"
import asyncio
from workers.worker_enrichment import EnrichmentWorker

async def process_once():
    worker = EnrichmentWorker(max_workers=3, batch_size=" . min($added, 50) . ", poll_interval=1)
    processed = await worker.process_batch()
    if processed > 0:
        worker.update_batch_counters()
    print(f'Обработано: {processed}')

asyncio.run(process_once())
\" >> /tmp/enrichment_trigger.log 2>&1 &";

            shell_exec($cmd);
            error_log("[Batch Create] Triggered immediate worker processing for $added records");
        }
    }

    // Возвращаем результат
    $response = [
        "success" => true,
        "batch_id" => intval($batch_id),
        "batch_name" => $batch_name,
        "added" => $added,
        "duplicates" => $duplicates,
        "invalid" => count($phones) - $added - $duplicates,
        "total_parsed" => count($phones),
        "total_records" => $added,
        "phones" => $added_phones,
        "worker_triggered" => $added > 0, // Указываем, что воркер запущен
    ];

    if (!empty($errors)) {
        $response["errors"] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        "error" => "Ошибка при создании batch: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
