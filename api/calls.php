<?php
/**
 * API для получения списка звонков с фильтрацией
 * GET /api/calls.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

// Получаем параметры фильтрации
$department = isset($_GET['department']) ? $_GET['department'] : '';
$manager = isset($_GET['manager']) ? $_GET['manager'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$duration_min = isset($_GET['duration_min']) ? intval($_GET['duration_min']) : 0;
$duration_max = isset($_GET['duration_max']) ? intval($_GET['duration_max']) : 999999;
$client_phone = isset($_GET['client_phone']) ? $_GET['client_phone'] : '';
$rating_min = isset($_GET['rating_min']) ? floatval($_GET['rating_min']) : 0;
$rating_max = isset($_GET['rating_max']) ? floatval($_GET['rating_max']) : 1;
$call_type = isset($_GET['call_type']) ? $_GET['call_type'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'started_at_utc';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

$offset = ($page - 1) * $per_page;

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Базовый запрос с JOIN для получения полной информации
$query = "SELECT
    cr.callid,
    cr.client_phone,
    cr.employee_name,
    cr.department,
    cr.direction,
    cr.duration_sec,
    cr.started_at_utc,
    cr.call_url,
    ar.call_type,
    ar.summary_text,
    ar.score_overall,
    ar.conversion_probability,
    ar.emotion_tone,
    t.audio_duration_sec,
    aj.local_path as audio_path,
    aj.status as audio_status
FROM calls_raw cr
LEFT JOIN analysis_results ar ON cr.callid = ar.callid
LEFT JOIN transcripts t ON cr.callid = t.callid
LEFT JOIN audio_jobs aj ON cr.callid = aj.callid
WHERE 1=1";

$params = [];

// Фильтр по отделу
if (!empty($department)) {
    $query .= " AND cr.department = :department";
    $params[':department'] = $department;
}

// Фильтр по менеджеру
if (!empty($manager)) {
    $query .= " AND cr.employee_name LIKE :manager";
    $params[':manager'] = '%' . $manager . '%';
}

// Фильтр по дате (от)
if (!empty($date_from)) {
    $query .= " AND DATE(cr.started_at_utc) >= :date_from";
    $params[':date_from'] = $date_from;
}

// Фильтр по дате (до)
if (!empty($date_to)) {
    $query .= " AND DATE(cr.started_at_utc) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Фильтр по длительности
if ($duration_min > 0) {
    $query .= " AND cr.duration_sec >= :duration_min";
    $params[':duration_min'] = $duration_min;
}
if ($duration_max < 999999) {
    $query .= " AND cr.duration_sec <= :duration_max";
    $params[':duration_max'] = $duration_max;
}

// Фильтр по номеру клиента
if (!empty($client_phone)) {
    $query .= " AND cr.client_phone LIKE :client_phone";
    $params[':client_phone'] = '%' . $client_phone . '%';
}

// Фильтр по оценке (score_overall от 0 до 10)
if ($rating_min > 0 || $rating_max < 1) {
    // Преобразуем 0-1 в 0-10 для score_overall
    $query .= " AND ar.score_overall BETWEEN :rating_min AND :rating_max";
    $params[':rating_min'] = $rating_min * 10;
    $params[':rating_max'] = $rating_max * 10;
}

// Фильтр по типу звонка
if (!empty($call_type)) {
    $query .= " AND ar.call_type = :call_type";
    $params[':call_type'] = $call_type;
}

// Подсчет общего количества записей
$count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as filtered";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetch()['total'];

// Добавляем сортировку и пагинацию
$allowed_sort_fields = ['started_at_utc', 'employee_name', 'department', 'duration_sec', 'score_overall'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'started_at_utc';
}

$query .= " ORDER BY " . $sort_by . " " . $sort_order;
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

// Формируем ответ
$response = [
    "success" => true,
    "data" => $calls,
    "pagination" => [
        "total" => intval($total_count),
        "page" => $page,
        "per_page" => $per_page,
        "total_pages" => ceil($total_count / $per_page)
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
