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

// Запрос KPI метрик - учитываем ВСЕ звонки (без фильтра по длительности)
$where_clause = implode(' AND ', $where_conditions);

$query = "
    SELECT
        COUNT(DISTINCT cr.callid) as total_calls,

        -- Первые звонки (is_first_call = 1 из calls_raw)
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 THEN cr.callid END) as first_calls,

        -- Повторные звонки (is_first_call = 0 из calls_raw)
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 THEN cr.callid END) as repeat_calls,

        -- Несостоявшиеся звонки (duration_sec <= 30)
        COUNT(DISTINCT CASE WHEN cr.duration_sec <= 30 THEN cr.callid END) as failed_calls,

        -- Показ назначен (все варианты назначенного/подтвержденного показа)
        COUNT(DISTINCT CASE
            WHEN ar.call_result IN ('Назначен показ', 'Подтвержден показ', 'Показ назначен')
              OR ar.call_result LIKE 'Показ назначен%'
              OR ar.call_result LIKE '%Результат: Показ назначен%'
            THEN cr.callid
        END) as showing_scheduled,

        -- Показ состоялся (только проведенные показы)
        COUNT(DISTINCT CASE
            WHEN ar.call_result = 'Показ проведен'
              OR ar.call_result = 'Показ состоялся'
              OR ar.call_result LIKE 'Показ состоялся%'
              OR ar.call_result LIKE '%Результат: Показ состоялся%'
            THEN cr.callid
        END) as showing_completed,

        COUNT(DISTINCT ar.client_phone) as unique_clients,

        -- Средний процент соответствия чеклистам
        (SELECT ROUND(AVG(compliance_pct), 0)
         FROM (
             SELECT
                 CASE
                     WHEN COUNT(*) = 0 THEN NULL
                     ELSE 100.0 * SUM(CASE WHEN aa.answer_value IN ('ДА', 'YES', 'True', '1') THEN 1 ELSE 0 END) / COUNT(*)
                 END as compliance_pct
             FROM analysis_results ar2
             LEFT JOIN analysis_answers aa ON aa.analysis_result_id = ar2.id
             WHERE ar2.callid IN (SELECT cr2.callid FROM calls_raw cr2 WHERE $where_clause)
             GROUP BY ar2.callid
             HAVING COUNT(*) > 0
         ) AS compliance_data
        ) as avg_compliance_percentage
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
            'first_calls' => (int)$row['first_calls'],
            'repeat_calls' => (int)$row['repeat_calls'],
            'failed_calls' => (int)$row['failed_calls'],
            'showing_scheduled' => (int)$row['showing_scheduled'],
            'showing_completed' => (int)$row['showing_completed'],
            'unique_clients' => (int)$row['unique_clients'],
            'avg_compliance_percentage' => $row['avg_compliance_percentage'] ? (int)$row['avg_compliance_percentage'] : null
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
