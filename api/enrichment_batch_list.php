<?php
/**
 * API для получения списка загрузок (batches)
 * GET /api/enrichment_batch_list.php
 *
 * ВАЖНО: Доступ только для администраторов!
 *
 * Параметры GET:
 * - limit (int) - количество записей (по умолчанию: все)
 *
 * Ответ JSON:
 * {
 *   "batches": [
 *     {
 *       "id": 123,
 *       "batch_name": "Клиенты Москва январь 2025",
 *       "created_at": "2025-10-27 21:00:00",
 *       "created_by": "Admin",
 *       "total_records": 50,
 *       "processed_records": 30,
 *       "completed_records": 28,
 *       "error_records": 2,
 *       "pending_records": 20,
 *       "inn_found_count": 20,
 *       "status": "processing",
 *       "progress_percent": 60
 *     }
 *   ]
 * }
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';

// КРИТИЧЕСКИ ВАЖНО: Проверка админских прав (API режим)
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

// Получаем параметры
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

try {
    // Запрос на получение батчей
    $query = "SELECT
        id,
        batch_name,
        created_at,
        created_by,
        total_records,
        processed_records,
        completed_records,
        error_records,
        pending_records,
        inn_found_count,
        status,
        completed_at
    FROM enrichment_batches
    ORDER BY created_at DESC";

    if ($limit) {
        $query .= " LIMIT :limit";
    }

    $stmt = $db->prepare($query);

    if ($limit) {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    }

    $stmt->execute();
    $batches_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Обогащаем данные
    $batches = [];
    foreach ($batches_raw as $batch) {
        // Вычисляем прогресс в процентах
        $total = intval($batch['total_records']);
        $processed = intval($batch['processed_records']);

        $progress_percent = $total > 0 ? round(($processed / $total) * 100) : 0;

        $batches[] = [
            'id' => intval($batch['id']),
            'batch_name' => $batch['batch_name'],
            'created_at' => $batch['created_at'],
            'created_by' => $batch['created_by'],
            'total_records' => $total,
            'processed_records' => $processed,
            'completed_records' => intval($batch['completed_records']),
            'error_records' => intval($batch['error_records']),
            'pending_records' => intval($batch['pending_records']),
            'inn_found_count' => intval($batch['inn_found_count']),
            'status' => $batch['status'],
            'completed_at' => $batch['completed_at'],
            'progress_percent' => $progress_percent,
        ];
    }

    echo json_encode([
        'success' => true,
        'batches' => $batches,
        'total_batches' => count($batches)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Ошибка при получении списка batch: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
