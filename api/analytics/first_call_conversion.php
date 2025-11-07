<?php
/**
 * API для получения конверсии первых звонков vs повторных
 * GET /api/analytics/first_call_conversion.php
 *
 * Параметры:
 * - date_from: дата начала (YYYY-MM-DD)
 * - date_to: дата окончания (YYYY-MM-DD)
 * - departments: список отделов через запятую (опционально)
 * - managers: список менеджеров через запятую (опционально)
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
$where_conditions = [
    "cr.started_at_utc >= :date_from",
    "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)"
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
// Обновлено 2025-11-03: увеличен порог с 10 до 30 секунд
// Исключаем все несостоявшиеся звонки из аналитики конверсии
}

$where_clause = implode(' AND ', $where_conditions);

// Запрос конверсии по менеджерам (первые vs повторные звонки)
$query = "
    SELECT
        ar.employee_full_name as manager_name,
        ar.employee_department as department,

        -- Первые звонки
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 THEN ar.callid END) as first_total,
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 AND ar.is_successful = 1 THEN ar.callid END) as first_successful,
        ROUND(
            100.0 * COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 AND ar.is_successful = 1 THEN ar.callid END) /
            NULLIF(COUNT(DISTINCT CASE WHEN cr.is_first_call = 1 THEN ar.callid END), 0),
            1
        ) as first_conversion_rate,

        -- Повторные звонки
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 THEN ar.callid END) as repeat_total,
        COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 AND ar.is_successful = 1 THEN ar.callid END) as repeat_successful,
        ROUND(
            100.0 * COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 AND ar.is_successful = 1 THEN ar.callid END) /
            NULLIF(COUNT(DISTINCT CASE WHEN cr.is_first_call = 0 THEN ar.callid END), 0),
            1
        ) as repeat_conversion_rate,

        -- Общие показатели
        COUNT(DISTINCT ar.callid) as total_calls,
        COUNT(DISTINCT CASE WHEN ar.is_successful = 1 THEN ar.callid END) as total_successful,
        ROUND(
            100.0 * COUNT(DISTINCT CASE WHEN ar.is_successful = 1 THEN ar.callid END) /
            NULLIF(COUNT(DISTINCT ar.callid), 0),
            1
        ) as overall_conversion_rate

    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
      AND ar.employee_full_name IS NOT NULL
      AND ar.employee_full_name != ''
    GROUP BY ar.employee_full_name, ar.employee_department
    HAVING COUNT(DISTINCT ar.callid) > 0
    ORDER BY first_conversion_rate IS NULL, first_conversion_rate DESC, repeat_conversion_rate DESC
";

try {
    $stmt = $db->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Форматируем данные для ECharts
    $managers_list = [];
    $departments_list = [];

    $first_conversion_data = [];
    $repeat_conversion_data = [];
    $overall_conversion_data = [];

    $first_total_data = [];
    $repeat_total_data = [];
    $total_calls_data = [];

    if (empty($rows)) {
        echo json_encode([
            'success' => true,
            'data' => [
                'managers' => [],
                'departments' => [],
                'first_conversion' => [],
                'repeat_conversion' => [],
                'overall_conversion' => [],
                'first_total' => [],
                'repeat_total' => [],
                'total_calls' => [],
                'summary' => [
                    'total_managers' => 0,
                    'avg_first_conversion' => 0,
                    'avg_repeat_conversion' => 0,
                    'avg_overall_conversion' => 0
                ]
            ],
            'message' => 'No data found for selected filters',
            'filters' => [
                'date_from' => $date_from,
                'date_to' => $date_to,
                'departments' => $departments,
                'managers' => $managers
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Подсчитываем средние значения
    $total_first_conv = 0;
    $total_repeat_conv = 0;
    $total_overall_conv = 0;
    $count_first = 0;
    $count_repeat = 0;
    $count_overall = 0;

    foreach ($rows as $row) {
        $managers_list[] = $row['manager_name'];
        $departments_list[] = $row['department'] ?: 'Не указан';

        $first_conv = (float)($row['first_conversion_rate'] ?? 0);
        $repeat_conv = (float)($row['repeat_conversion_rate'] ?? 0);
        $overall_conv = (float)($row['overall_conversion_rate'] ?? 0);

        $first_conversion_data[] = $first_conv;
        $repeat_conversion_data[] = $repeat_conv;
        $overall_conversion_data[] = $overall_conv;

        $first_total_data[] = (int)$row['first_total'];
        $repeat_total_data[] = (int)$row['repeat_total'];
        $total_calls_data[] = (int)$row['total_calls'];

        // Считаем средние (только если есть звонки)
        if ($row['first_total'] > 0) {
            $total_first_conv += $first_conv;
            $count_first++;
        }
        if ($row['repeat_total'] > 0) {
            $total_repeat_conv += $repeat_conv;
            $count_repeat++;
        }
        if ($row['total_calls'] > 0) {
            $total_overall_conv += $overall_conv;
            $count_overall++;
        }
    }

    $avg_first_conversion = $count_first > 0 ? round($total_first_conv / $count_first, 1) : 0;
    $avg_repeat_conversion = $count_repeat > 0 ? round($total_repeat_conv / $count_repeat, 1) : 0;
    $avg_overall_conversion = $count_overall > 0 ? round($total_overall_conv / $count_overall, 1) : 0;

    $response = [
        'success' => true,
        'data' => [
            'managers' => $managers_list,
            'departments' => $departments_list,
            'first_conversion' => $first_conversion_data,
            'repeat_conversion' => $repeat_conversion_data,
            'overall_conversion' => $overall_conversion_data,
            'first_total' => $first_total_data,
            'repeat_total' => $repeat_total_data,
            'total_calls' => $total_calls_data,
            'summary' => [
                'total_managers' => count($rows),
                'avg_first_conversion' => $avg_first_conversion,
                'avg_repeat_conversion' => $avg_repeat_conversion,
                'avg_overall_conversion' => $avg_overall_conversion,
                'difference' => round($avg_first_conversion - $avg_repeat_conversion, 1)
            ]
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
