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

// Отключаем кеширование для получения актуальных aggregate_summary
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Получаем параметры фильтрации
$departments = isset($_GET['departments']) ? $_GET['departments'] : ''; // Множественный выбор
$managers = isset($_GET['managers']) ? $_GET['managers'] : ''; // Множественный выбор
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$duration_range = isset($_GET['duration_range']) ? $_GET['duration_range'] : '';
$hide_short_calls = isset($_GET['hide_short_calls']) ? $_GET['hide_short_calls'] : '1'; // По умолчанию включен
$client_phone = isset($_GET['client_phone']) ? $_GET['client_phone'] : '';
$call_id = isset($_GET['call_id']) ? trim($_GET['call_id']) : ''; // Поиск по ID звонка
$directions = isset($_GET['directions']) ? $_GET['directions'] : ''; // Множественный выбор
$ratings = isset($_GET['ratings']) ? $_GET['ratings'] : ''; // Множественный выбор (high,medium,low)
$call_type = isset($_GET['call_type']) ? $_GET['call_type'] : '';
$call_results = isset($_GET['call_results']) ? $_GET['call_results'] : ''; // Множественный выбор результатов
$tags = isset($_GET['tags']) ? $_GET['tags'] : ''; // Множественный выбор
$crm_stages = isset($_GET['crm_stages']) ? $_GET['crm_stages'] : ''; // Множественный выбор CRM этапов (формат: "funnel1:step1,funnel2:step2")
$solvency_levels = isset($_GET['solvency_levels']) ? $_GET['solvency_levels'] : ''; // Множественный выбор платежеспособности
$client_statuses = isset($_GET['client_statuses']) ? $_GET['client_statuses'] : ''; // Множественный выбор статусов клиента
$batch_id = isset($_GET['batch_id']) ? trim($_GET['batch_id']) : ''; // Фильтр по пакетному анализу
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
// client_enrichment всегда включен для отображения aggregate_summary и solvency_level
$query = "SELECT
    cr.callid,
    cr.client_phone,
    cr.employee_name,
    cr.department,
    cr.direction,
    cr.duration_sec,
    cr.started_at_utc,
    cr.call_url,
    cr.is_first_call,
    ar.call_type,
    ar.summary_text,
    ar.is_successful,
    ar.call_result,
    COALESCE(ar.script_compliance_score_v4, ar.script_compliance_score) as script_compliance_score,
    ar.template_id,
    at.name as template_name,
    ar.compliance_score,
    ar.crm_funnel_name,
    ar.crm_step_name,
    t.audio_duration_sec,
    aj.local_path as audio_path,
    aj.status as audio_status,
    ct.tag_type,
    ct.note as tag_note,
    ct.tagged_at,
    ct.tagged_by_user,
    ce.aggregate_summary,
    ce.client_overall_status,
    ce.solvency_level,
    ce.total_calls_count,
    (
        SELECT
            CASE
                WHEN COUNT(*) = 0 THEN NULL
                ELSE ROUND(100.0 * SUM(CASE WHEN aa.answer_value IN ('ДА', 'YES', 'True', '1') THEN 1 ELSE 0 END) / COUNT(*), 0)
            END
        FROM analysis_answers aa
        WHERE aa.analysis_result_id = ar.id
    ) as compliance_percentage
FROM calls_raw cr
LEFT JOIN analysis_results ar ON cr.callid = ar.callid
LEFT JOIN analysis_templates at ON ar.template_id = at.template_id
LEFT JOIN transcripts t ON cr.callid = t.callid
LEFT JOIN audio_jobs aj ON cr.callid = aj.callid
LEFT JOIN call_tags ct ON cr.callid = ct.callid
LEFT JOIN client_enrichment ce ON CONCAT('+7', cr.client_phone) = ce.client_phone";

$params = [];

$query .= "\nWHERE 1=1";

// Фильтрация галлюцинаций Whisper (спам-транскрипты)
$query .= " AND (t.is_hallucinated IS NULL OR t.is_hallucinated = 0)";

// Фильтрация по организации (multi-tenancy)
$org_id = $_SESSION['org_id'] ?? 'org-legacy';
$query .= " AND cr.org_id = :org_id";
$params[':org_id'] = $org_id;

// Фильтрация по batch_id (пакетный анализ)
if (!empty($batch_id)) {
    // Показываем только звонки из конкретного пакета
    $query .= " AND cr.callid IN (SELECT bci.callid FROM batch_call_items bci WHERE bci.batch_id = :batch_id)";
    $params[':batch_id'] = $batch_id;
} else {
    // Скрыть uploaded звонки (upl-*) с главной страницы - они видны через batch_calls.php
    $query .= " AND cr.callid NOT LIKE 'upl-%'";
}

// Фильтрация по отделам пользователя (если не admin и не rop)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'rop' && !empty($user_departments)) {
    $placeholders = [];
    foreach ($user_departments as $index => $dept) {
        $param_name = ':user_dept_' . $index;
        $placeholders[] = $param_name;
        $params[$param_name] = $dept;
    }
    $query .= " AND cr.department IN (" . implode(', ', $placeholders) . ")";
}

// Фильтр по отделам (множественный выбор по department_id)
if (!empty($departments)) {
    $departments_array = explode(',', $departments);
    $departments_placeholders = [];
    foreach ($departments_array as $index => $dept) {
        $param_name = ':department_id_' . $index;
        $departments_placeholders[] = $param_name;
        $params[$param_name] = intval($dept);
    }
    $query .= " AND cr.department_id IN (" . implode(', ', $departments_placeholders) . ")";
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

// Фильтр по длительности (новый формат диапазонов)
if (!empty($duration_range)) {
    $range_parts = explode('-', $duration_range);
    if (count($range_parts) === 2) {
        $duration_min = intval($range_parts[0]);
        $duration_max = intval($range_parts[1]);

        $query .= " AND cr.duration_sec >= :duration_min AND cr.duration_sec <= :duration_max";
        $params[':duration_min'] = $duration_min;
        $params[':duration_max'] = $duration_max;
    }
}

// Фильтр "Скрыть до 20 сек" (toggle переключатель)
if ($hide_short_calls === '1') {
    $query .= " AND cr.duration_sec > 20";
}

// Фильтр по номеру клиента
if (!empty($client_phone)) {
    $query .= " AND cr.client_phone LIKE :client_phone";
    $params[':client_phone'] = '%' . $client_phone . '%';
}

// Фильтр по ID звонка (точное или частичное совпадение)
// Убираем точки для совместимости с Moodle (clean_param удаляет точки)
if (!empty($call_id)) {
    $query .= " AND REPLACE(cr.callid, '.', '') LIKE :call_id";
    $params[':call_id'] = '%' . str_replace('.', '', $call_id) . '%';
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
// compliance_score хранится как проценты 0-100
if (!empty($ratings)) {
    $ratings_array = explode(',', $ratings);
    $rating_conditions = [];

    foreach ($ratings_array as $rating) {
        $rating = trim($rating);
        $score_field = 'ar.compliance_score';
        if ($rating === 'high') {
            // Высокая: 60-100%
            $rating_conditions[] = "($score_field >= 60 AND $score_field <= 100)";
        } elseif ($rating === 'medium') {
            // Средняя: 30-60%
            $rating_conditions[] = "($score_field >= 30 AND $score_field < 60)";
        } elseif ($rating === 'low') {
            // Низкая: 0-30%
            $rating_conditions[] = "($score_field >= 0 AND $score_field < 30)";
        }
    }

    if (!empty($rating_conditions)) {
        $query .= " AND (" . implode(' OR ', $rating_conditions) . ")";
    }
}

// Фильтр по типу звонка (3 типа: первичный, повторный, несостоявшийся)
if (!empty($call_type)) {
    if ($call_type === 'failed_call') {
        // Несостоявшийся: короткие звонки (≤30 сек)
        $query .= " AND cr.duration_sec <= 30";
    } elseif ($call_type === 'first_call') {
        // Первичный: is_first_call=1 И длительность >30 сек
        $query .= " AND cr.is_first_call = 1 AND cr.duration_sec > 30";
    } elseif ($call_type === 'repeat_call') {
        // Повторный: is_first_call=0 И длительность >30 сек
        $query .= " AND cr.is_first_call = 0 AND cr.duration_sec > 30";
    }
    // Убрали поддержку устаревшего значения 'other'
}

// Фильтр по результату звонка (успешный/неуспешный)
if (!empty($call_results)) {
    $results_array = explode(',', $call_results);
    $result_conditions = [];

    foreach ($results_array as $result) {
        $result = trim($result);

        if ($result === 'successful') {
            $result_conditions[] = "ar.is_successful = 1";
        } elseif ($result === 'unsuccessful') {
            $result_conditions[] = "(ar.is_successful = 0 OR ar.is_successful IS NULL)";
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

// Фильтр по CRM этапам (множественный выбор, формат: "Покупатели:Новый лид,Продавец:Квалификация")
if (!empty($crm_stages)) {
    $stages_array = explode(',', $crm_stages);
    $crm_conditions = [];

    foreach ($stages_array as $index => $stage) {
        $stage = trim($stage);
        $parts = explode(':', $stage, 2); // Разбиваем на воронку и этап

        if (count($parts) === 2) {
            $funnel = trim($parts[0]);
            $step = trim($parts[1]);

            $funnel_param = ':crm_funnel_' . $index;
            $step_param = ':crm_step_' . $index;

            $crm_conditions[] = "(ar.crm_funnel_name = $funnel_param AND ar.crm_step_name = $step_param)";
            $params[$funnel_param] = $funnel;
            $params[$step_param] = $step;
        }
    }

    if (!empty($crm_conditions)) {
        $query .= " AND (" . implode(' OR ', $crm_conditions) . ")";
    }
}

// Фильтр по платежеспособности (множественный выбор: green,blue,yellow,red)
if (!empty($solvency_levels)) {
    $solvency_array = explode(',', $solvency_levels);
    $solvency_placeholders = [];
    foreach ($solvency_array as $index => $level) {
        $param_name = ':solvency_' . $index;
        $solvency_placeholders[] = $param_name;
        $params[$param_name] = trim($level);
    }
    $query .= " AND ce.solvency_level IN (" . implode(', ', $solvency_placeholders) . ")";
}

// Фильтр по статусу клиента (множественный выбор из фиксированного набора)
if (!empty($client_statuses)) {
    $statuses_array = explode(',', $client_statuses);
    $statuses_placeholders = [];
    foreach ($statuses_array as $index => $status) {
        $param_name = ':client_status_' . $index;
        $statuses_placeholders[] = $param_name;
        $params[$param_name] = trim($status);
    }
    $query .= " AND ce.client_overall_status IN (" . implode(', ', $statuses_placeholders) . ")";
}

// Подсчет общего количества записей
// ОПТИМИЗАЦИЯ: Считаем только по calls_raw + минимум JOIN'ов (в 40x быстрее)
$count_query = "SELECT COUNT(DISTINCT cr.callid) as total FROM calls_raw cr";

// Добавляем JOIN'ы только если используются фильтры из этих таблиц
$needs_ar_join = !empty($call_type) || !empty($call_results) || !empty($ratings) || !empty($crm_stages);
$needs_ct_join = !empty($tags);
// client_enrichment нужен для фильтра по solvency_levels и client_statuses
$needs_ce_join = !empty($solvency_levels) || !empty($client_statuses);

// transcripts всегда нужен для фильтра галлюцинаций
$count_query .= "\nLEFT JOIN transcripts t ON cr.callid = t.callid";

if ($needs_ar_join) {
    $count_query .= "\nLEFT JOIN analysis_results ar ON cr.callid = ar.callid";
}
if ($needs_ct_join) {
    $count_query .= "\nLEFT JOIN call_tags ct ON cr.callid = ct.callid";
}
if ($needs_ce_join) {
    $count_query .= "\nLEFT JOIN client_enrichment ce ON CONCAT('+7', cr.client_phone) = ce.client_phone";
}

// Копируем WHERE условия из основного запроса
$where_clause = substr($query, strpos($query, 'WHERE 1=1'));
$where_clause = substr($where_clause, 0, strpos($where_clause, 'ORDER BY') ?: strlen($where_clause));
$count_query .= "\n" . trim($where_clause);

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
