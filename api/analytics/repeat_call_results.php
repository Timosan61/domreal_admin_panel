<?php
/**
 * API для получения распределения результатов повторного звонка по менеджерам
 * GET /api/analytics/repeat_call_results.php
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

// Запрос распределения результатов повторного звонка по менеджерам
$query = "
    SELECT
        ar.employee_full_name as manager_name,
        ar.employee_department as department,
        COUNT(DISTINCT ar.callid) as total_calls,

        -- 14 категорий результатов
        SUM(CASE WHEN ar.call_result = 'Назначен показ' THEN 1 ELSE 0 END) as result_show_scheduled,
        SUM(CASE WHEN ar.call_result = 'Подтвержден показ' THEN 1 ELSE 0 END) as result_show_confirmed,
        SUM(CASE WHEN ar.call_result = 'Показ проведен' THEN 1 ELSE 0 END) as result_show_done,
        SUM(CASE WHEN ar.call_result = 'Отправлены новые варианты' THEN 1 ELSE 0 END) as result_materials_sent,
        SUM(CASE WHEN ar.call_result = 'Клиент подтвердил интерес' THEN 1 ELSE 0 END) as result_interest_confirmed,

        SUM(CASE WHEN ar.call_result = 'Отложенное решение' THEN 1 ELSE 0 END) as result_thinking,
        SUM(CASE WHEN ar.call_result = 'Ожидается ответ клиента' THEN 1 ELSE 0 END) as result_waiting,
        SUM(CASE WHEN ar.call_result = 'Назначена консультация' THEN 1 ELSE 0 END) as result_consultation,

        SUM(CASE WHEN ar.call_result = 'Недозвон / не отвечает' THEN 1 ELSE 0 END) as result_no_answer,
        SUM(CASE WHEN ar.call_result = 'Отказ / неактуально' THEN 1 ELSE 0 END) as result_rejection,
        SUM(CASE WHEN ar.call_result = 'Не целевой клиент' THEN 1 ELSE 0 END) as result_not_target,
        SUM(CASE WHEN ar.call_result = 'Личный/нерабочий' THEN 1 ELSE 0 END) as result_personal,

        SUM(CASE WHEN ar.call_result = 'Бронь / задаток' THEN 1 ELSE 0 END) as result_booking,
        SUM(CASE WHEN ar.call_result = 'Сделка закрыта' THEN 1 ELSE 0 END) as result_deal_closed

    FROM calls_raw cr
    INNER JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $where_clause
      AND ar.employee_full_name IS NOT NULL
      AND ar.employee_full_name != ''
      AND ar.call_result IS NOT NULL
    GROUP BY ar.employee_full_name, ar.employee_department
    HAVING COUNT(DISTINCT ar.callid) > 0
    ORDER BY total_calls DESC
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

    // Распределение по категориям
    $distribution = [
        'show_scheduled' => [],
        'show_confirmed' => [],
        'show_done' => [],
        'materials_sent' => [],
        'interest_confirmed' => [],
        'thinking' => [],
        'waiting' => [],
        'consultation' => [],
        'no_answer' => [],
        'rejection' => [],
        'not_target' => [],
        'personal' => [],
        'booking' => [],
        'deal_closed' => []
    ];

    if (empty($rows)) {
        echo json_encode([
            'success' => true,
            'data' => [
                'managers' => [],
                'departments' => [],
                'total_calls' => [],
                'distribution' => $distribution
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

        $distribution['show_scheduled'][] = (int)$row['result_show_scheduled'];
        $distribution['show_confirmed'][] = (int)$row['result_show_confirmed'];
        $distribution['show_done'][] = (int)$row['result_show_done'];
        $distribution['materials_sent'][] = (int)$row['result_materials_sent'];
        $distribution['interest_confirmed'][] = (int)$row['result_interest_confirmed'];
        $distribution['thinking'][] = (int)$row['result_thinking'];
        $distribution['waiting'][] = (int)$row['result_waiting'];
        $distribution['consultation'][] = (int)$row['result_consultation'];
        $distribution['no_answer'][] = (int)$row['result_no_answer'];
        $distribution['rejection'][] = (int)$row['result_rejection'];
        $distribution['not_target'][] = (int)$row['result_not_target'];
        $distribution['personal'][] = (int)$row['result_personal'];
        $distribution['booking'][] = (int)$row['result_booking'];
        $distribution['deal_closed'][] = (int)$row['result_deal_closed'];
    }

    $response = [
        'success' => true,
        'data' => [
            'managers' => $managers_list,
            'departments' => $departments_list,
            'total_calls' => $total_calls_data,
            'distribution' => $distribution
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
