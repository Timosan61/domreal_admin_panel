<?php
/**
 * API для получения данных обогащения клиентов
 * GET /api/enrichment_data.php
 *
 * ВАЖНО: Доступ только для администраторов!
 */

// === PERFORMANCE PROFILING ===
$perf_start = microtime(true);
error_log("[PERF] enrichment_data.php - Request started at " . date('Y-m-d H:i:s'));

// Увеличим лимиты для обработки больших выборок
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '120');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();

// Логируем информацию о сессии для отладки
error_log("[AUTH] enrichment_data.php - Session ID: " . session_id());
error_log("[AUTH] enrichment_data.php - User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
error_log("[AUTH] enrichment_data.php - Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET'));

require_once '../auth/session.php';

// КРИТИЧЕСКИ ВАЖНО: Проверка админских прав (API режим)
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

// Получаем параметры фильтрации
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$enriched_date_from = isset($_GET['enriched_date_from']) ? $_GET['enriched_date_from'] : '';
$enriched_date_to = isset($_GET['enriched_date_to']) ? $_GET['enriched_date_to'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$inn_filter = isset($_GET['inn_filter']) ? $_GET['inn_filter'] : '';
$source_filter = isset($_GET['source_filter']) ? $_GET['source_filter'] : '';
$data_source_filter = isset($_GET['data_source_filter']) ? $_GET['data_source_filter'] : '';
$phone_search = isset($_GET['phone_search']) ? $_GET['phone_search'] : '';
$solvency_levels = isset($_GET['solvency_levels']) ? $_GET['solvency_levels'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 50;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

// Если запрошена статистика
if (isset($_GET['stats']) && $_GET['stats'] === '1') {
    $perf_stats_start = microtime(true);

    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        http_response_code(503);
        echo json_encode(["error" => "Database connection failed"]);
        exit();
    }

    $perf_db_connected = microtime(true);
    error_log(sprintf("[PERF] DB connected in %.3fs", $perf_db_connected - $perf_stats_start));

    // Общая статистика
    $stats_query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN inn IS NOT NULL AND inn != '' THEN 1 ELSE 0 END) as with_inn,
            SUM(CASE WHEN rusprofile_parsed = TRUE THEN 1 ELSE 0 END) as with_rusprofile,
            SUM(CASE WHEN solvency_analyzed = TRUE THEN 1 ELSE 0 END) as with_solvency,
            SUM(CASE WHEN enrichment_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN enrichment_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN enrichment_status = 'error' THEN 1 ELSE 0 END) as errors,
            SUM(CASE WHEN enrichment_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN userbox_searched = TRUE THEN 1 ELSE 0 END) as userbox_searched,
            SUM(databases_checked) as total_databases_checked,
            SUM(databases_found) as total_databases_found
        FROM client_enrichment
    ";

    $perf_query1_start = microtime(true);
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    $perf_query1_end = microtime(true);
    error_log(sprintf("[PERF] Stats query executed in %.3fs", $perf_query1_end - $perf_query1_start));

    // Статистика по источникам ИНН
    $sources_query = "
        SELECT
            inn_source,
            COUNT(*) as count
        FROM client_enrichment
        WHERE inn IS NOT NULL AND inn_source IS NOT NULL
        GROUP BY inn_source
        ORDER BY count DESC
        LIMIT 10
    ";

    $perf_query2_start = microtime(true);
    $sources_stmt = $db->prepare($sources_query);
    $sources_stmt->execute();
    $sources = $sources_stmt->fetchAll(PDO::FETCH_ASSOC);
    $perf_query2_end = microtime(true);
    error_log(sprintf("[PERF] Sources query executed in %.3fs", $perf_query2_end - $perf_query2_start));

    // Статистика по уровням платежеспособности
    $solvency_query = "
        SELECT
            solvency_level,
            COUNT(*) as count
        FROM client_enrichment
        WHERE solvency_level IS NOT NULL
        GROUP BY solvency_level
        ORDER BY count DESC
    ";

    $perf_query3_start = microtime(true);
    $solvency_stmt = $db->prepare($solvency_query);
    $solvency_stmt->execute();
    $solvency_levels = $solvency_stmt->fetchAll(PDO::FETCH_ASSOC);
    $perf_query3_end = microtime(true);
    error_log(sprintf("[PERF] Solvency query executed in %.3fs", $perf_query3_end - $perf_query3_start));

    // Вычисление экономии Userbox
    // Базовая стоимость: 20 баз × 0.125₽ = 2.5₽ без оптимизации
    // С /explain: только по базам с данными
    $avg_databases_checked = $stats['total'] > 0 ? $stats['total_databases_checked'] / $stats['total'] : 0;
    $base_cost = $stats['total'] * 20 * 0.125; // Без оптимизации
    $actual_cost = $stats['total_databases_checked'] * 0.125; // С оптимизацией
    $savings = $base_cost - $actual_cost;
    $savings_percent = $base_cost > 0 ? ($savings / $base_cost * 100) : 0;

    $response = [
        "success" => true,
        "stats" => [
            "total" => intval($stats['total']),
            "with_inn" => intval($stats['with_inn']),
            "inn_coverage" => $stats['total'] > 0 ? round($stats['with_inn'] / $stats['total'] * 100, 1) : 0,
            "userbox_searched" => intval($stats['userbox_searched']),
            "with_rusprofile" => intval($stats['with_rusprofile']),
            "rusprofile_coverage" => $stats['with_inn'] > 0 ? round($stats['with_rusprofile'] / $stats['with_inn'] * 100, 1) : 0,
            "with_solvency" => intval($stats['with_solvency']),
            "solvency_coverage" => $stats['with_inn'] > 0 ? round($stats['with_solvency'] / $stats['with_inn'] * 100, 1) : 0,
            "completed" => intval($stats['completed']),
            "in_progress" => intval($stats['in_progress']),
            "errors" => intval($stats['errors']),
            "pending" => intval($stats['pending']),
            "userbox_savings" => [
                "base_cost" => round($base_cost, 2),
                "actual_cost" => round($actual_cost, 2),
                "savings" => round($savings, 2),
                "savings_percent" => round($savings_percent, 1),
                "avg_databases_checked" => round($avg_databases_checked, 1),
                "total_databases_found" => intval($stats['total_databases_found'])
            ]
        ],
        "sources" => $sources,
        "solvency_levels" => $solvency_levels
    ];

    $perf_stats_total = microtime(true) - $perf_stats_start;
    error_log(sprintf("[PERF] Stats endpoint completed in %.3fs (total from start: %.3fs)",
        $perf_stats_total, microtime(true) - $perf_start));

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Основной запрос данных
$perf_data_start = microtime(true);
$offset = ($page - 1) * $per_page;

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

$perf_db_connected = microtime(true);
error_log(sprintf("[PERF] DB connected in %.3fs", $perf_db_connected - $perf_data_start));

// Базовый запрос
$query = "SELECT
    id,
    client_phone,
    inn,
    inn_source,
    enrichment_status,
    created_at,
    updated_at,
    userbox_searched,
    databases_found,
    databases_checked,
    rusprofile_parsed,
    company_name,
    company_full_name,
    ogrn,
    director_name,
    revenue_last_year,
    profit_last_year,
    number_of_employees,
    number_of_founders,
    solvency_analyzed,
    solvency_level,
    solvency_summary,
    rusprofile_parse_date as rusprofile_parsed_at,
    solvency_analysis_date as solvency_analyzed_at,
    apifns_companies_count,
    apifns_total_revenue,
    apifns_total_profit,
    -- DaData новые поля
    dadata_companies_count,
    dadata_total_revenue,
    dadata_total_profit,
    dadata_founders,
    dadata_managers,
    dadata_phones,
    dadata_emails,
    dadata_address,
    dadata_registration_date,
    data_source,
    company_inn,
    company_solvency_score
FROM client_enrichment
WHERE 1=1";

$params = [];

// Фильтр по дате создания записи (от)
if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

// Фильтр по дате создания записи (до)
if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Фильтр по дате обогащения/обновления (от)
if (!empty($enriched_date_from)) {
    $query .= " AND DATE(updated_at) >= :enriched_date_from";
    $params[':enriched_date_from'] = $enriched_date_from;
}

// Фильтр по дате обогащения/обновления (до)
if (!empty($enriched_date_to)) {
    $query .= " AND DATE(updated_at) <= :enriched_date_to";
    $params[':enriched_date_to'] = $enriched_date_to;
}

// Фильтр по статусу обогащения
if (!empty($status)) {
    $query .= " AND enrichment_status = :status";
    $params[':status'] = $status;
}

// Фильтр по наличию ИНН
if ($inn_filter === 'yes') {
    $query .= " AND inn IS NOT NULL AND inn != ''";
} elseif ($inn_filter === 'no') {
    $query .= " AND (inn IS NULL OR inn = '')";
}

// Фильтр по источнику ИНН
if (!empty($source_filter)) {
    $query .= " AND inn_source LIKE :source_filter";
    $params[':source_filter'] = '%' . $source_filter . '%';
}

// Фильтр по источнику данных (DaData / API-ФНС)
if (!empty($data_source_filter)) {
    $query .= " AND data_source = :data_source_filter";
    $params[':data_source_filter'] = $data_source_filter;
}

// Фильтр по номеру телефона
if (!empty($phone_search)) {
    $query .= " AND client_phone LIKE :phone_search";
    $params[':phone_search'] = '%' . $phone_search . '%';
}

// Фильтр по уровням платежеспособности (мультиселект)
// ✨ ИСПРАВЛЕНО: Фильтруем по solvency_level (строка), а не company_solvency_score (число)
if (!empty($solvency_levels)) {
    $levels_array = explode(',', $solvency_levels);
    $levels_array = array_map('trim', $levels_array);
    $levels_array = array_filter($levels_array);

    if (!empty($levels_array)) {
        $placeholders = [];
        foreach ($levels_array as $i => $level) {
            $param_name = ':solvency_level_' . $i;
            $placeholders[] = $param_name;
            // Сохраняем строковое значение ('green', 'blue', etc)
            $params[$param_name] = $level;
        }
        // Фильтруем по solvency_level (строка), а не company_solvency_score
        $query .= " AND solvency_level IN (" . implode(', ', $placeholders) . ")";
    }
}

// Подсчет общего количества записей
// ОПТИМИЗАЦИЯ: Считаем без подзапроса (быстрее в 10-100x)
$count_query = "SELECT COUNT(*) as total FROM client_enrichment";

// Копируем WHERE условия из основного запроса
$where_clause = substr($query, strpos($query, 'WHERE 1=1'));
$count_query .= "\n" . trim($where_clause);

$perf_count_start = microtime(true);
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetch()['total'];
$perf_count_end = microtime(true);
error_log(sprintf("[PERF] COUNT query executed in %.3fs", $perf_count_end - $perf_count_start));

// Добавляем сортировку и пагинацию
$allowed_sort_fields = ['id', 'client_phone', 'inn', 'enrichment_status', 'created_at', 'updated_at', 'solvency_level'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
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

$perf_data_query_start = microtime(true);
try {
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[ERROR] enrichment_data.php - PDO error: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database query failed: " . $e->getMessage()
    ]);
    exit();
}
$perf_data_query_end = microtime(true);
error_log(sprintf("[PERF] Data query executed in %.3fs", $perf_data_query_end - $perf_data_query_start));

// Формируем ответ
$response = [
    "success" => true,
    "data" => $records,
    "pagination" => [
        "total" => intval($total_count),
        "page" => $page,
        "per_page" => $per_page,
        "total_pages" => ceil($total_count / $per_page)
    ]
];

$perf_data_total = microtime(true) - $perf_data_start;
error_log(sprintf("[PERF] Data endpoint completed in %.3fs (total from start: %.3fs)",
    $perf_data_total, microtime(true) - $perf_start));

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
