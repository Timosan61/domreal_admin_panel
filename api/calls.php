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

session_start();
require_once '../auth/session.php';
checkAuth(); // Проверка авторизации

include_once '../config/database.php';

// Получаем параметры фильтрации
$departments = isset($_GET['departments']) ? $_GET['departments'] : ''; // Множественный выбор
$managers = isset($_GET['managers']) ? $_GET['managers'] : ''; // Множественный выбор
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$duration_min = isset($_GET['duration_min']) ? intval($_GET['duration_min']) : 0;
$duration_max = isset($_GET['duration_max']) ? intval($_GET['duration_max']) : 999999;
$client_phone = isset($_GET['client_phone']) ? $_GET['client_phone'] : '';
$directions = isset($_GET['directions']) ? $_GET['directions'] : ''; // Множественный выбор
$ratings = isset($_GET['ratings']) ? $_GET['ratings'] : ''; // Множественный выбор (high,medium,low)
$call_type = isset($_GET['call_type']) ? $_GET['call_type'] : '';
$call_results = isset($_GET['call_results']) ? $_GET['call_results'] : ''; // Множественный выбор результатов
$tags = isset($_GET['tags']) ? $_GET['tags'] : ''; // Множественный выбор
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

// Получаем список отделов, доступных пользователю
$user_departments = getUserDepartments();

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
    ar.is_successful,
    ar.call_result,
    ar.script_compliance_score,
    t.audio_duration_sec,
    aj.local_path as audio_path,
    aj.status as audio_status,
    ct.tag_type,
    ct.note as tag_note,
    ct.tagged_at,
    ct.tagged_by_user
FROM calls_raw cr
LEFT JOIN analysis_results ar ON cr.callid = ar.callid
LEFT JOIN transcripts t ON cr.callid = t.callid
LEFT JOIN audio_jobs aj ON cr.callid = aj.callid
LEFT JOIN call_tags ct ON cr.callid = ct.callid
WHERE 1=1";

$params = [];

// Фильтрация по отделам пользователя (если не admin)
if ($_SESSION['role'] !== 'admin' && !empty($user_departments)) {
    $placeholders = [];
    foreach ($user_departments as $index => $dept) {
        $param_name = ':user_dept_' . $index;
        $placeholders[] = $param_name;
        $params[$param_name] = $dept;
    }
    $query .= " AND cr.department IN (" . implode(', ', $placeholders) . ")";
}

// Фильтр по отделам (множественный выбор)
if (!empty($departments)) {
    $departments_array = explode(',', $departments);
    $departments_placeholders = [];
    foreach ($departments_array as $index => $dept) {
        $param_name = ':department_' . $index;
        $departments_placeholders[] = $param_name;
        $params[$param_name] = $dept;
    }
    $query .= " AND cr.department IN (" . implode(', ', $departments_placeholders) . ")";
}

// Фильтр по менеджерам (множественный выбор)
if (!empty($managers)) {
    $managers_array = explode(',', $managers);
    $managers_placeholders = [];
    foreach ($managers_array as $index => $manager) {
        $param_name = ':manager_' . $index;
        $managers_placeholders[] = $param_name;
        $params[$param_name] = $manager;
    }
    $query .= " AND cr.employee_name IN (" . implode(', ', $managers_placeholders) . ")";
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

// Фильтр по направлениям звонка (множественный выбор, формат: "INBOUND,OUTBOUND")
if (!empty($directions)) {
    $directions_array = explode(',', $directions);
    $directions_placeholders = [];
    foreach ($directions_array as $index => $dir) {
        $param_name = ':direction_' . $index;
        $directions_placeholders[] = $param_name;
        $params[$param_name] = trim($dir);
    }
    $query .= " AND cr.direction IN (" . implode(', ', $directions_placeholders) . ")";
}

// Фильтр по оценке (множественный выбор: high,medium,low)
if (!empty($ratings)) {
    $ratings_array = explode(',', $ratings);
    $rating_conditions = [];

    foreach ($ratings_array as $rating) {
        $rating = trim($rating);
        if ($rating === 'high') {
            $rating_conditions[] = '(ar.script_compliance_score >= 0.8 AND ar.script_compliance_score <= 1.0)';
        } elseif ($rating === 'medium') {
            $rating_conditions[] = '(ar.script_compliance_score >= 0.6 AND ar.script_compliance_score < 0.8)';
        } elseif ($rating === 'low') {
            $rating_conditions[] = '(ar.script_compliance_score >= 0 AND ar.script_compliance_score < 0.6)';
        }
    }

    if (!empty($rating_conditions)) {
        $query .= " AND (" . implode(' OR ', $rating_conditions) . ")";
    }
}

// Фильтр по типу звонка
if (!empty($call_type)) {
    $query .= " AND ar.call_type = :call_type";
    $params[':call_type'] = $call_type;
}

// Фильтр по результату звонка (множественный выбор через LIKE)
if (!empty($call_results)) {
    $results_array = explode(',', $call_results);
    $result_conditions = [];

    foreach ($results_array as $result) {
        $result = trim($result);

        // Категории первого звонка
        if ($result === 'квалификация') {
            $result_conditions[] = "LOWER(ar.call_result) LIKE '%квалифик%'";
        } elseif ($result === 'материалы') {
            $result_conditions[] = "(LOWER(ar.call_result) LIKE '%материал%' OR LOWER(ar.call_result) LIKE '%отправ%')";
        } elseif ($result === 'назначен перезвон') {
            $result_conditions[] = "(LOWER(ar.call_result) LIKE '%назначен перезвон%' OR (LOWER(ar.call_result) LIKE '%перезвон%' AND ar.call_type = 'first_call'))";
        }

        // Категории других звонков
        elseif ($result === 'показ назначен') {
            $result_conditions[] = "LOWER(ar.call_result) LIKE '%показ назначен%'";
        } elseif ($result === 'показ состоялся') {
            $result_conditions[] = "LOWER(ar.call_result) LIKE '%показ состоялся%'";
        } elseif ($result === 'показ') {
            $result_conditions[] = "LOWER(ar.call_result) LIKE '%показ%'";
        } elseif ($result === 'перезвон') {
            $result_conditions[] = "(LOWER(ar.call_result) LIKE '%перезвон%' AND ar.call_type != 'first_call')";
        } elseif ($result === 'думает') {
            $result_conditions[] = "LOWER(ar.call_result) LIKE '%думает%'";
        }

        // Общие категории
        elseif ($result === 'отказ') {
            $result_conditions[] = "LOWER(ar.call_result) LIKE '%отказ%'";
        } elseif ($result === 'не целевой') {
            $result_conditions[] = "(LOWER(ar.call_result) LIKE '%не целевой%' OR LOWER(ar.call_result) LIKE '%нецелевой%')";
        } elseif ($result === 'не дозвонились') {
            $result_conditions[] = "(LOWER(ar.call_result) LIKE '%не дозвон%' OR LOWER(ar.call_result) LIKE '%автоответчик%')";
        } elseif ($result === 'личный') {
            $result_conditions[] = "(LOWER(ar.call_result) LIKE '%личн%' OR LOWER(ar.call_result) LIKE '%нерабоч%')";
        }
    }

    if (!empty($result_conditions)) {
        $query .= " AND (" . implode(' OR ', $result_conditions) . ")";
    }
}

// Фильтр по тегам (множественный выбор: good,bad,question)
if (!empty($tags)) {
    $tags_array = explode(',', $tags);
    $tags_placeholders = [];
    foreach ($tags_array as $index => $tag) {
        $param_name = ':tag_' . $index;
        $tags_placeholders[] = $param_name;
        $params[$param_name] = trim($tag);
    }
    $query .= " AND ct.tag_type IN (" . implode(', ', $tags_placeholders) . ")";
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
$allowed_sort_fields = ['started_at_utc', 'employee_name', 'department', 'duration_sec', 'direction', 'script_compliance_score'];
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
