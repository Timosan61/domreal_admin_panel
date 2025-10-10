<?php
/**
 * API для получения данных воронки конверсии
 * GET /api/analytics/funnel.php
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

// Запрос для воронки конверсии
$query = "
    SELECT
        COUNT(DISTINCT cr.callid) as total_calls,
        COUNT(DISTINCT CASE
            WHEN ar.call_result LIKE '%Перезвон%' OR ar.call_result LIKE '%перезвон%'
            THEN cr.callid
        END) as callback_scheduled,
        COUNT(DISTINCT CASE
            WHEN LOWER(ar.call_result) LIKE '%показ%'
            THEN cr.callid
        END) as show_scheduled,
        COUNT(DISTINCT CASE
            WHEN LOWER(ar.call_result) = 'показ'
              OR LOWER(ar.call_result) LIKE '%показ состоялся%'
            THEN cr.callid
        END) as show_completed
    FROM calls_raw cr
    LEFT JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Формируем данные воронки
    $funnel_data = [
        [
            'name' => 'Всего звонков',
            'value' => (int)$row['total_calls']
        ],
        [
            'name' => 'Перезвон назначен',
            'value' => (int)$row['callback_scheduled']
        ],
        [
            'name' => 'Показ назначен',
            'value' => (int)$row['show_scheduled']
        ],
        [
            'name' => 'Показ состоялся',
            'value' => (int)$row['show_completed']
        ]
    ];

    $response = [
        'success' => true,
        'data' => $funnel_data,
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
