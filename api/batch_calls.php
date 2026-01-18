<?php
/**
 * API для получения звонков пакетного анализа
 * GET /api/batch_calls.php?batch_id=xxx
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Cache-Control: no-cache, no-store, must-revalidate");

session_start();
require_once '../auth/session.php';
checkAuth(false, true);

include_once '../config/database.php';

// Обязательный параметр batch_id
$batch_id = isset($_GET['batch_id']) ? trim($_GET['batch_id']) : '';
if (empty($batch_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "batch_id is required"]);
    exit();
}

// Параметры пагинации и сортировки
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'call_date';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
$offset = ($page - 1) * $per_page;

// Получаем org_id из сессии
$org_id = $_SESSION['org_id'] ?? 'org-legacy';

// Подключаемся к БД организации
$database = new Database();
$db = $database->getConnection($org_id);

if ($db === null) {
    http_response_code(503);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit();
}

// Проверяем существование батча и получаем его данные
$batchQuery = "SELECT batch_id, batch_name, status, total_calls, org_id
               FROM batch_analysis_jobs
               WHERE batch_id = :batch_id";
$batchStmt = $db->prepare($batchQuery);
$batchStmt->bindValue(':batch_id', $batch_id);
$batchStmt->execute();
$batch = $batchStmt->fetch();

if (!$batch) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Batch not found"]);
    exit();
}

// Проверка доступа по org_id
if ($batch['org_id'] !== $org_id) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Access denied"]);
    exit();
}

// Запрос звонков батча из batch_call_items (все данные уже там)
$query = "SELECT
    bci.callid,
    bci.file_name,
    bci.employee_name,
    bci.client_phone,
    bci.call_date as started_at_utc,
    bci.call_duration_sec as duration_sec,
    bci.status as batch_item_status,
    bci.l1_summary,
    bci.l1_parsed_results,
    bci.error_message,
    bci.created_at
FROM batch_call_items bci
WHERE bci.batch_id = :batch_id";

$params = [':batch_id' => $batch_id];

// Подсчёт общего количества
$countQuery = "SELECT COUNT(*) as total FROM batch_call_items WHERE batch_id = :batch_id_count";
$countStmt = $db->prepare($countQuery);
$countStmt->bindValue(':batch_id_count', $batch_id);
$countStmt->execute();
$total_count = $countStmt->fetch()['total'];

// Валидация поля сортировки
$allowed_sort_fields = ['call_date', 'employee_name', 'call_duration_sec', 'batch_item_status', 'created_at'];
$sort_field_map = [
    'started_at_utc' => 'call_date',
    'duration_sec' => 'call_duration_sec'
];
if (isset($sort_field_map[$sort_by])) {
    $sort_by = $sort_field_map[$sort_by];
}
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'call_date';
}

// Добавляем сортировку и пагинацию
$query .= " ORDER BY bci." . $sort_by . " " . $sort_order;
$query .= " LIMIT :limit OFFSET :offset";

// Выполняем запрос
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$calls = $stmt->fetchAll();

// Декодируем JSON поля
foreach ($calls as &$call) {
    if (!empty($call['l1_parsed_results'])) {
        $call['l1_parsed_results'] = json_decode($call['l1_parsed_results'], true);
    }
}
unset($call);

// Формируем ответ
$response = [
    "success" => true,
    "batch" => [
        "batch_id" => $batch['batch_id'],
        "batch_name" => $batch['batch_name'],
        "status" => $batch['status'],
        "total_calls" => $batch['total_calls']
    ],
    "data" => $calls,
    "pagination" => [
        "total" => intval($total_count),
        "page" => $page,
        "per_page" => $per_page,
        "total_pages" => ceil($total_count / $per_page)
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
