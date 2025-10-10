<?php
/**
 * API для получения статистики выполнения скрипта первого звонка
 * GET /api/analytics/script_quality.php
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

// Базовый WHERE с датами и только first_call
$where_conditions = [
    "cr.started_at_utc >= :date_from",
    "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)",
    "ar.call_type = 'first_call'"
];
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

// Запрос статистики выполнения скрипта
$query = "
    SELECT
        COUNT(DISTINCT ar.callid) as total_first_calls,
        SUM(CASE WHEN ar.script_check_location = 1 THEN 1 ELSE 0 END) as location_checked,
        SUM(CASE WHEN ar.script_check_payment = 1 THEN 1 ELSE 0 END) as payment_checked,
        SUM(CASE WHEN ar.script_check_goal = 1 THEN 1 ELSE 0 END) as goal_checked,
        SUM(CASE WHEN ar.script_check_is_local = 1 THEN 1 ELSE 0 END) as is_local_checked,
        SUM(CASE WHEN ar.script_check_budget = 1 THEN 1 ELSE 0 END) as budget_checked,
        AVG(CASE WHEN ar.script_compliance_score IS NOT NULL THEN ar.script_compliance_score END) as avg_compliance_score
    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = (int)$row['total_first_calls'];

    // Вычисляем проценты выполнения для каждого пункта скрипта
    $script_items = [
        [
            'name' => 'Местоположение клиента',
            'checked' => (int)$row['location_checked'],
            'percentage' => $total > 0 ? round(((int)$row['location_checked'] / $total) * 100, 1) : 0
        ],
        [
            'name' => 'Форма оплаты',
            'checked' => (int)$row['payment_checked'],
            'percentage' => $total > 0 ? round(((int)$row['payment_checked'] / $total) * 100, 1) : 0
        ],
        [
            'name' => 'Цель покупки',
            'checked' => (int)$row['goal_checked'],
            'percentage' => $total > 0 ? round(((int)$row['goal_checked'] / $total) * 100, 1) : 0
        ],
        [
            'name' => 'Местный ли клиент',
            'checked' => (int)$row['is_local_checked'],
            'percentage' => $total > 0 ? round(((int)$row['is_local_checked'] / $total) * 100, 1) : 0
        ],
        [
            'name' => 'Бюджет',
            'checked' => (int)$row['budget_checked'],
            'percentage' => $total > 0 ? round(((int)$row['budget_checked'] / $total) * 100, 1) : 0
        ]
    ];

    $response = [
        'success' => true,
        'data' => [
            'total_first_calls' => $total,
            'avg_compliance_score' => $row['avg_compliance_score'] ? round((float)$row['avg_compliance_score'], 2) : 0,
            'script_items' => $script_items
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
