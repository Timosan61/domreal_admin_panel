<?php
/**
 * API для получения оценок повторного звонка по менеджерам
 * GET /api/analytics/repeat_call_scores.php
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

// Базовый WHERE с датами и фильтром повторного звонка
$where_conditions = [
    "cr.started_at_utc >= :date_from",
    "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)",
    "cr.is_first_call = 0"  // Только повторные звонки
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

// Запрос оценок повторного звонка
$query = "
    SELECT
        ar.employee_full_name as manager_name,
        ar.employee_department as department,
        COUNT(DISTINCT ar.callid) as total_calls,
        ROUND(AVG(CASE WHEN ar.script_compliance_score_v4 IS NOT NULL
            THEN ar.script_compliance_score_v4 END) * 100, 1) as avg_score,

        -- Распределение по диапазонам оценок (0-25%, 25-50%, 50-75%, 75-100%)
        SUM(CASE
            WHEN ar.script_compliance_score_v4 >= 0 AND ar.script_compliance_score_v4 < 0.25
            THEN 1 ELSE 0
        END) as score_0_25,

        SUM(CASE
            WHEN ar.script_compliance_score_v4 >= 0.25 AND ar.script_compliance_score_v4 < 0.50
            THEN 1 ELSE 0
        END) as score_25_50,

        SUM(CASE
            WHEN ar.script_compliance_score_v4 >= 0.50 AND ar.script_compliance_score_v4 < 0.75
            THEN 1 ELSE 0
        END) as score_50_75,

        SUM(CASE
            WHEN ar.script_compliance_score_v4 >= 0.75 AND ar.script_compliance_score_v4 <= 1.0
            THEN 1 ELSE 0
        END) as score_75_100
    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
      AND ar.employee_full_name IS NOT NULL
      AND ar.employee_full_name != ''
      AND ar.script_compliance_score_v4 IS NOT NULL
    GROUP BY ar.employee_full_name, ar.employee_department
    HAVING COUNT(DISTINCT ar.callid) > 0
    ORDER BY avg_score DESC
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
    $total_calls_data = [];
    $avg_scores = [];
    $score_0_25_data = [];
    $score_25_50_data = [];
    $score_50_75_data = [];
    $score_75_100_data = [];

    if (empty($rows)) {
        echo json_encode([
            'success' => true,
            'data' => [
                'managers' => [],
                'departments' => [],
                'total_calls' => [],
                'avg_scores' => [],
                'score_distribution' => [
                    'score_0_25' => [],
                    'score_25_50' => [],
                    'score_50_75' => [],
                    'score_75_100' => []
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

    foreach ($rows as $row) {
        $managers_list[] = $row['manager_name'];
        $departments_list[] = $row['department'] ?: 'Не указан';
        $total_calls_data[] = (int)$row['total_calls'];
        $avg_scores[] = $row['avg_score'] ? round((float)$row['avg_score'], 1) : 0;
        $score_0_25_data[] = (int)$row['score_0_25'];
        $score_25_50_data[] = (int)$row['score_25_50'];
        $score_50_75_data[] = (int)$row['score_50_75'];
        $score_75_100_data[] = (int)$row['score_75_100'];
    }

    $response = [
        'success' => true,
        'data' => [
            'managers' => $managers_list,
            'departments' => $departments_list,
            'total_calls' => $total_calls_data,
            'avg_scores' => $avg_scores,
            'score_distribution' => [
                'score_0_25' => $score_0_25_data,
                'score_25_50' => $score_25_50_data,
                'score_50_75' => $score_50_75_data,
                'score_75_100' => $score_75_100_data
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
