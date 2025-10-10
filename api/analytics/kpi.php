<?php
/**
 * API для получения KPI метрик
 * GET /api/analytics/kpi.php
 *
 * Параметры:
 * - date_from: дата начала (YYYY-MM-DD)
 * - date_to: дата окончания (YYYY-MM-DD)
 * - departments[]: массив отделов (опционально)
 * - managers[]: массив менеджеров (опционально)
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
$params = [':date_from' => $date_from . ' 00:00:00', ':date_to' => $date_to];

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

$where_clause = implode(' AND ', $where_conditions);

// Запрос KPI метрик
$query = "
    SELECT
        COUNT(DISTINCT cr.callid) as total_calls,
        COUNT(DISTINCT CASE WHEN ar.callid IS NOT NULL THEN cr.callid END) as analyzed_calls,
        COUNT(DISTINCT CASE
            WHEN LOWER(ar.call_result) LIKE '%показ%'
            THEN cr.callid
        END) as showing_scheduled,
        COUNT(DISTINCT CASE
            WHEN LOWER(ar.call_result) = 'показ'
              OR LOWER(ar.call_result) LIKE '%показ состоялся%'
            THEN cr.callid
        END) as showing_completed,
        COUNT(DISTINCT CASE WHEN ar.call_type = 'first_call' THEN cr.callid END) as first_calls,
        AVG(CASE WHEN ar.script_compliance_score IS NOT NULL THEN ar.script_compliance_score END) as avg_script_score,
        COUNT(DISTINCT ar.client_phone) as unique_clients
    FROM calls_raw cr
    LEFT JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'data' => [
            'total_calls' => (int)$row['total_calls'],
            'analyzed_calls' => (int)$row['analyzed_calls'],
            'showing_scheduled' => (int)$row['showing_scheduled'],
            'showing_completed' => (int)$row['showing_completed'],
            'first_calls' => (int)$row['first_calls'],
            'unique_clients' => (int)$row['unique_clients'],
            'avg_script_score' => $row['avg_script_score'] ? round($row['avg_script_score'], 2) : 0
        ],
        'filters' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'departments' => $departments,
            'managers' => $managers
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
