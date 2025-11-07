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

// Запрос статистики выполнения скрипта (поддержка v3 и v4)
$query = "
    SELECT
        COUNT(DISTINCT ar.callid) as total_first_calls,

        -- v3 statistics (5 пунктов)
        SUM(CASE WHEN ar.script_version = 'v3' OR ar.script_version IS NULL THEN 1 ELSE 0 END) as total_v3,
        SUM(CASE WHEN (ar.script_version = 'v3' OR ar.script_version IS NULL) AND ar.script_check_location = 1 THEN 1 ELSE 0 END) as v3_location_checked,
        SUM(CASE WHEN (ar.script_version = 'v3' OR ar.script_version IS NULL) AND ar.script_check_payment = 1 THEN 1 ELSE 0 END) as v3_payment_checked,
        SUM(CASE WHEN (ar.script_version = 'v3' OR ar.script_version IS NULL) AND ar.script_check_goal = 1 THEN 1 ELSE 0 END) as v3_goal_checked,
        SUM(CASE WHEN (ar.script_version = 'v3' OR ar.script_version IS NULL) AND ar.script_check_is_local = 1 THEN 1 ELSE 0 END) as v3_is_local_checked,
        SUM(CASE WHEN (ar.script_version = 'v3' OR ar.script_version IS NULL) AND ar.script_check_budget = 1 THEN 1 ELSE 0 END) as v3_budget_checked,
        AVG(CASE WHEN (ar.script_version = 'v3' OR ar.script_version IS NULL) AND ar.script_compliance_score IS NOT NULL THEN ar.script_compliance_score END) as v3_avg_score,

        -- v4 statistics (6 пунктов)
        SUM(CASE WHEN ar.script_version = 'v4' THEN 1 ELSE 0 END) as total_v4,
        SUM(CASE WHEN ar.script_version = 'v4' AND ar.script_check_v4_interest = 1 THEN 1 ELSE 0 END) as v4_interest_checked,
        SUM(CASE WHEN ar.script_version = 'v4' AND ar.script_check_v4_location = 1 THEN 1 ELSE 0 END) as v4_location_checked,
        SUM(CASE WHEN ar.script_version = 'v4' AND ar.script_check_v4_payment = 1 THEN 1 ELSE 0 END) as v4_payment_checked,
        SUM(CASE WHEN ar.script_version = 'v4' AND ar.script_check_v4_goal = 1 THEN 1 ELSE 0 END) as v4_goal_checked,
        SUM(CASE WHEN ar.script_version = 'v4' AND ar.script_check_v4_history = 1 THEN 1 ELSE 0 END) as v4_history_checked,
        SUM(CASE WHEN ar.script_version = 'v4' AND ar.script_check_v4_action = 1 THEN 1 ELSE 0 END) as v4_action_checked,
        AVG(CASE WHEN ar.script_version = 'v4' AND ar.script_compliance_score_v4 IS NOT NULL THEN ar.script_compliance_score_v4 END) as v4_avg_score
    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = (int)$row['total_first_calls'];
    $total_v3 = (int)$row['total_v3'];
    $total_v4 = (int)$row['total_v4'];

    // Определяем, какую версию показывать (приоритет v4)
    $use_v4 = $total_v4 > 0;

    if ($use_v4) {
        // Используем статистику v4 (6 пунктов)
        $script_items = [
            [
                'name' => '5.1. Установка контекста и интерес',
                'checked' => (int)$row['v4_interest_checked'],
                'percentage' => $total_v4 > 0 ? round(((int)$row['v4_interest_checked'] / $total_v4) * 100, 1) : 0
            ],
            [
                'name' => '5.2. В Сочи? Локация и срочность',
                'checked' => (int)$row['v4_location_checked'],
                'percentage' => $total_v4 > 0 ? round(((int)$row['v4_location_checked'] / $total_v4) * 100, 1) : 0
            ],
            [
                'name' => '5.3. Финансовые условия',
                'checked' => (int)$row['v4_payment_checked'],
                'percentage' => $total_v4 > 0 ? round(((int)$row['v4_payment_checked'] / $total_v4) * 100, 1) : 0
            ],
            [
                'name' => '5.4. Цель покупки',
                'checked' => (int)$row['v4_goal_checked'],
                'percentage' => $total_v4 > 0 ? round(((int)$row['v4_goal_checked'] / $total_v4) * 100, 1) : 0
            ],
            [
                'name' => '5.5. История просмотров',
                'checked' => (int)$row['v4_history_checked'],
                'percentage' => $total_v4 > 0 ? round(((int)$row['v4_history_checked'] / $total_v4) * 100, 1) : 0
            ],
            [
                'name' => '5.6. Немедленное действие',
                'checked' => (int)$row['v4_action_checked'],
                'percentage' => $total_v4 > 0 ? round(((int)$row['v4_action_checked'] / $total_v4) * 100, 1) : 0
            ]
        ];
        $avg_score = $row['v4_avg_score'] ? round((float)$row['v4_avg_score'], 2) : 0;
        $script_version = 'v4';
    } else {
        // Используем статистику v3 (5 пунктов) - для обратной совместимости
        $script_items = [
            [
                'name' => 'Местоположение клиента',
                'checked' => (int)$row['v3_location_checked'],
                'percentage' => $total_v3 > 0 ? round(((int)$row['v3_location_checked'] / $total_v3) * 100, 1) : 0
            ],
            [
                'name' => 'Форма оплаты',
                'checked' => (int)$row['v3_payment_checked'],
                'percentage' => $total_v3 > 0 ? round(((int)$row['v3_payment_checked'] / $total_v3) * 100, 1) : 0
            ],
            [
                'name' => 'Цель покупки',
                'checked' => (int)$row['v3_goal_checked'],
                'percentage' => $total_v3 > 0 ? round(((int)$row['v3_goal_checked'] / $total_v3) * 100, 1) : 0
            ],
            [
                'name' => 'Местный ли клиент',
                'checked' => (int)$row['v3_is_local_checked'],
                'percentage' => $total_v3 > 0 ? round(((int)$row['v3_is_local_checked'] / $total_v3) * 100, 1) : 0
            ],
            [
                'name' => 'Бюджет',
                'checked' => (int)$row['v3_budget_checked'],
                'percentage' => $total_v3 > 0 ? round(((int)$row['v3_budget_checked'] / $total_v3) * 100, 1) : 0
            ]
        ];
        $avg_score = $row['v3_avg_score'] ? round((float)$row['v3_avg_score'], 2) : 0;
        $script_version = 'v3';
    }

    $response = [
        'success' => true,
        'data' => [
            'total_first_calls' => $total,
            'total_v3' => $total_v3,
            'total_v4' => $total_v4,
            'script_version' => $script_version,
            'avg_compliance_score' => $avg_score,
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
