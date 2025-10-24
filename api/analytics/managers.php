<?php
/**
 * API для получения топ менеджеров
 * GET /api/analytics/managers.php
 *
 * Параметры:
 * - date_from: дата начала (YYYY-MM-DD)
 * - date_to: дата окончания (YYYY-MM-DD)
 * - departments[]: массив отделов (опционально)
 * - managers[]: массив менеджеров (опционально)
 * - limit: количество топ менеджеров (по умолчанию 10)
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

session_start();
require_once '../../auth/session.php';
checkAuth();

include_once '../../config/database.php';

// Параметры фильтрации
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$departments = isset($_GET['departments']) ? $_GET['departments'] : '';
$managers = isset($_GET['managers']) ? $_GET['managers'] : '';
$hide_short_calls = isset($_GET['hide_short_calls']) ? $_GET['hide_short_calls'] : '1';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

// Подключение к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем список отделов пользователя
$user_departments = getUserDepartments();

// Базовый WHERE с датами
$where_conditions = ["cr.started_at_utc >= :date_from", "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)"];
$params = [':date_from' => $date_from . ' 00:00:00', ':date_to' => $date_to, ':limit' => $limit];

// Фильтр по отделам пользователя
if ($_SESSION['role'] !== 'admin' && !empty($user_departments)) {
    $placeholders = [];
    foreach ($user_departments as $idx => $dept) {
        $key = ":user_dept_$idx";
        $placeholders[] = $key;
        $params[$key] = $dept;
    }
    $where_conditions[] = "cr.department IN (" . implode(',', $placeholders) . ")";
}

// Фильтр по выбранным отделам
if (!empty($departments)) {
    $dept_array = explode(',', $departments);
    $dept_placeholders = [];
    foreach ($dept_array as $idx => $dept) {
        $key = ":dept_$idx";
        $dept_placeholders[] = $key;
        $params[$key] = trim($dept);
    }
    $where_conditions[] = "cr.department IN (" . implode(',', $dept_placeholders) . ")";
}

// Фильтр по менеджерам
if (!empty($managers)) {
    $manager_array = explode(',', $managers);
    $manager_placeholders = [];
    foreach ($manager_array as $idx => $manager) {
        $key = ":manager_$idx";
        $manager_placeholders[] = $key;
        $params[$key] = trim($manager);
    }
    $where_conditions[] = "ar.employee_full_name IN (" . implode(',', $manager_placeholders) . ")";
}

// Фильтр "Скрыть до 10 сек"
if ($hide_short_calls === '1') {
    $where_conditions[] = "cr.duration_sec > 10";
}

$where_clause = implode(' AND ', $where_conditions);

// Запрос топ менеджеров
$query = "
    SELECT
        ar.employee_full_name as manager_name,
        ar.employee_department as department,
        COUNT(DISTINCT ar.callid) as total_calls,
        COUNT(DISTINCT CASE
            WHEN LOWER(ar.call_result) LIKE '%показ%'
            THEN ar.callid
        END) as showing_scheduled,
        COUNT(DISTINCT CASE
            WHEN LOWER(ar.call_result) = 'показ'
              OR LOWER(ar.call_result) LIKE '%показ состоялся%'
            THEN ar.callid
        END) as showing_completed,
        ROUND(
            CASE
                WHEN COUNT(DISTINCT ar.callid) > 0
                THEN (COUNT(DISTINCT CASE
                    WHEN LOWER(ar.call_result) LIKE '%показ%'
                    THEN ar.callid
                END) * 100.0 / COUNT(DISTINCT ar.callid))
                ELSE 0
            END,
            1
        ) as showing_rate,
        ROUND(
            AVG(CASE WHEN ar.script_compliance_score IS NOT NULL
                THEN ar.script_compliance_score END) * 100,
            1
        ) as avg_script_score
    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause AND ar.employee_full_name IS NOT NULL AND ar.employee_full_name != ''
    GROUP BY ar.employee_full_name, ar.employee_department
    ORDER BY showing_completed DESC, showing_scheduled DESC
    LIMIT :limit
";

try {
    $stmt = $db->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        if ($key === ':limit') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Форматируем данные для ECharts
    $managers_list = [];
    $total_calls_data = [];
    $showing_scheduled_data = [];
    $showing_completed_data = [];
    $showing_rates = [];
    $script_scores = [];
    $departments_list = [];

    if (empty($rows)) {
        // Если нет данных - вернем пустой массив с успехом
        echo json_encode([
            'success' => true,
            'data' => [
                'managers' => [],
                'departments' => [],
                'total_calls' => [],
                'showing_scheduled' => [],
                'showing_completed' => [],
                'showing_rates' => [],
                'script_scores' => []
            ],
            'message' => 'No data found for selected filters',
            'filters' => [
                'date_from' => $date_from,
                'date_to' => $date_to,
                'departments' => $departments,
                'managers' => $managers,
                'limit' => $limit
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    foreach ($rows as $row) {
        $managers_list[] = $row['manager_name'];
        $departments_list[] = $row['department'] ?: 'Не указан';
        $total_calls_data[] = (int)$row['total_calls'];
        $showing_scheduled_data[] = (int)$row['showing_scheduled'];
        $showing_completed_data[] = (int)$row['showing_completed'];
        $showing_rates[] = (float)$row['showing_rate'];
        $script_scores[] = $row['avg_script_score'] ? round((float)$row['avg_script_score'], 1) : 0;
    }

    $response = [
        'success' => true,
        'data' => [
            'managers' => $managers_list,
            'departments' => $departments_list,
            'total_calls' => $total_calls_data,
            'showing_scheduled' => $showing_scheduled_data,
            'showing_completed' => $showing_completed_data,
            'showing_rates' => $showing_rates,
            'script_scores' => $script_scores
        ],
        'filters' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'departments' => $departments,
            'managers' => $managers,
            'limit' => $limit
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
