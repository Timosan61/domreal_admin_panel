<?php
/**
 * API для получения воронки покупателя по менеджерам
 * GET /api/analytics/funnel_by_manager.php
 *
 * Параметры:
 * - date_from: дата начала (YYYY-MM-DD)
 * - date_to: дата окончания (YYYY-MM-DD)
 * - departments[]: массив отделов (опционально)
 * - managers[]: массив менеджеров (опционально)
 * - mode: режим отображения (managers|departments|detailed, по умолчанию managers)
 *
 * Атрибуция клиентов: по последнему звонку
 * Статусы воронки: только активные этапы (Active + Waiting)
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
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'managers'; // managers|departments|detailed

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

// Базовый WHERE с датами для последнего звонка
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
    $where_conditions[] = "cr.employee_name IN (" . implode(',', $manager_placeholders) . ")";
}

$where_clause = implode(' AND ', $where_conditions);

// SQL запрос с атрибуцией по последнему звонку
// CTE для нахождения последнего звонка каждого клиента с данными JoyWork CRM
$query = "
WITH last_calls AS (
    SELECT
        ar.client_phone,
        ar.crm_step_name,
        cr.employee_name,
        ar.employee_department,
        cr.started_at_utc,
        ROW_NUMBER() OVER (PARTITION BY ar.client_phone ORDER BY cr.started_at_utc DESC) as rn
    FROM analysis_results ar
    INNER JOIN calls_raw cr ON ar.callid = cr.callid
    WHERE $where_clause
        AND ar.crm_step_name IS NOT NULL
        AND ar.crm_step_name != ''
        AND ar.crm_funnel_name = 'Покупатели'
)
SELECT
    " . ($mode === 'departments' ? "employee_department" : "employee_name") . " as entity_name,
    " . ($mode === 'detailed' ? "employee_department, employee_name," : "") . "
    crm_step_name,
    COUNT(DISTINCT client_phone) as leads_count
FROM last_calls
WHERE rn = 1
    AND employee_name IS NOT NULL
    AND employee_name != ''
    " . ($mode === 'departments' ? "AND employee_department IS NOT NULL AND employee_department != ''" : "") . "
GROUP BY
    " . ($mode === 'departments' ? "employee_department" : "employee_name") . ",
    " . ($mode === 'detailed' ? "employee_department, employee_name," : "") . "
    crm_step_name
ORDER BY
    " . ($mode === 'departments' ? "employee_department" : "employee_name") . ",
    " . ($mode === 'detailed' ? "employee_department, employee_name," : "") . "
    crm_step_name
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получить уникальные этапы CRM из результатов
    $funnel_stages = [];
    foreach ($rows as $row) {
        $stage = $row['crm_step_name'];
        if (!in_array($stage, $funnel_stages)) {
            $funnel_stages[] = $stage;
        }
    }
    // Если нет данных в результате, получаем этапы из всех доступных данных
    if (empty($funnel_stages)) {
        $stages_query = "
        SELECT DISTINCT ar.crm_step_name
        FROM analysis_results ar
        WHERE ar.crm_step_name IS NOT NULL
          AND ar.crm_step_name != ''
          AND ar.crm_funnel_name = 'Покупатели'
        ORDER BY ar.crm_step_name
        LIMIT 50
        ";
        $stages_stmt = $db->prepare($stages_query);
        $stages_stmt->execute();
        $funnel_stages = array_column($stages_stmt->fetchAll(PDO::FETCH_ASSOC), 'crm_step_name');
    }

    // Дополнительный запрос для получения общего количества звонков (не только в воронке)
    // Создаем отдельный WHERE без условий для client_enrichment
    $total_calls_where = [];
    $total_calls_params = [];

    // Базовые условия по датам
    $total_calls_where[] = "cr.started_at_utc >= :date_from";
    $total_calls_where[] = "cr.started_at_utc < DATE_ADD(:date_to, INTERVAL 1 DAY)";
    $total_calls_params[':date_from'] = $params[':date_from'];
    $total_calls_params[':date_to'] = $params[':date_to'];

    // Фильтр по отделам пользователя
    if ($_SESSION['role'] !== 'admin' && !empty($user_departments)) {
        $placeholders = [];
        foreach ($user_departments as $idx => $dept) {
            $key = ":tc_user_dept_$idx";
            $placeholders[] = $key;
            $total_calls_params[$key] = $dept;
        }
        $total_calls_where[] = "cr.department IN (" . implode(',', $placeholders) . ")";
    }

    // Фильтр по выбранным отделам
    if (!empty($departments)) {
        $dept_array = explode(',', $departments);
        $dept_placeholders = [];
        foreach ($dept_array as $idx => $dept) {
            $key = ":tc_dept_$idx";
            $dept_placeholders[] = $key;
            $total_calls_params[$key] = trim($dept);
        }
        $total_calls_where[] = "cr.department IN (" . implode(',', $dept_placeholders) . ")";
    }

    // Фильтр по менеджерам
    if (!empty($managers)) {
        $manager_array = explode(',', $managers);
        $manager_placeholders = [];
        foreach ($manager_array as $idx => $manager) {
            $key = ":tc_manager_$idx";
            $manager_placeholders[] = $key;
            $total_calls_params[$key] = trim($manager);
        }
        $total_calls_where[] = "cr.employee_name IN (" . implode(',', $manager_placeholders) . ")";
    }

    $total_calls_where_clause = implode(' AND ', $total_calls_where);

    $total_calls_query = "
    SELECT
        " . ($mode === 'departments' ? "ar.employee_department" : "cr.employee_name") . " as entity_name,
        " . ($mode === 'detailed' ? "ar.employee_department, cr.employee_name," : "") . "
        COUNT(DISTINCT cr.callid) as total_calls
    FROM calls_raw cr
    LEFT JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE $total_calls_where_clause
        AND cr.employee_name IS NOT NULL
        AND cr.employee_name != ''
        AND cr.duration_sec >= 60
        " . ($mode === 'departments' ? "AND ar.employee_department IS NOT NULL AND ar.employee_department != ''" : "") . "
    GROUP BY
        " . ($mode === 'departments' ? "ar.employee_department" : "cr.employee_name") .
        ($mode === 'detailed' ? ", ar.employee_department, cr.employee_name" : "") . "
    ";

    $total_calls_stmt = $db->prepare($total_calls_query);
    $total_calls_stmt->execute($total_calls_params);
    $total_calls_rows = $total_calls_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Создаем lookup для быстрого доступа к total_calls
    $total_calls_lookup = [];
    foreach ($total_calls_rows as $row) {
        if ($mode === 'detailed') {
            $key = $row['employee_department'] . '|' . $row['employee_name'];
            $total_calls_lookup[$key] = (int)$row['total_calls'];
        } else {
            $entity = $row['entity_name'];
            $total_calls_lookup[$entity] = (int)$row['total_calls'];
        }
    }

    // Обработка результатов в зависимости от режима
    $result = [];
    $stages_summary = array_fill_keys($funnel_stages, 0);
    $total_leads = 0;

    if ($mode === 'detailed') {
        // Группировка: отделы -> менеджеры
        $departments_data = [];

        foreach ($rows as $row) {
            $dept = $row['employee_department'] ?: 'Не указан';
            $manager = $row['employee_name'];
            $stage = $row['crm_step_name'];
            $count = (int)$row['leads_count'];

            if (!isset($departments_data[$dept])) {
                $departments_data[$dept] = [
                    'department' => $dept,
                    'managers' => [],
                    'total_leads' => 0,
                    'stages' => array_fill_keys($funnel_stages, 0)
                ];
            }

            if (!isset($departments_data[$dept]['managers'][$manager])) {
                $departments_data[$dept]['managers'][$manager] = [
                    'manager_name' => $manager,
                    'stages' => array_fill_keys($funnel_stages, 0),
                    'total_leads' => 0
                ];
            }

            $departments_data[$dept]['managers'][$manager]['stages'][$stage] = $count;
            $departments_data[$dept]['managers'][$manager]['total_leads'] += $count;
            $departments_data[$dept]['stages'][$stage] += $count;
            $departments_data[$dept]['total_leads'] += $count;
            $stages_summary[$stage] += $count;
            $total_leads += $count;
        }

        // Преобразуем в массив и добавляем total_calls
        $result = array_map(function($dept_data) use ($total_calls_lookup) {
            // Добавляем total_calls для каждого менеджера
            foreach ($dept_data['managers'] as $manager_name => &$manager_data) {
                $key = $dept_data['department'] . '|' . $manager_name;
                $manager_data['total_calls'] = $total_calls_lookup[$key] ?? 0;
            }
            unset($manager_data); // Разрываем ссылку

            // Подсчитываем total_calls для отдела (сумма по менеджерам)
            $dept_total_calls = 0;
            foreach ($dept_data['managers'] as $manager_data) {
                $dept_total_calls += $manager_data['total_calls'];
            }
            $dept_data['total_calls'] = $dept_total_calls;

            $dept_data['managers'] = array_values($dept_data['managers']);
            return $dept_data;
        }, array_values($departments_data));

    } else {
        // Простая группировка (по менеджерам или отделам)
        $entities = [];

        foreach ($rows as $row) {
            $entity_name = $row['entity_name'];
            $stage = $row['crm_step_name'];
            $count = (int)$row['leads_count'];

            if (!isset($entities[$entity_name])) {
                $entities[$entity_name] = [
                    ($mode === 'departments' ? 'department' : 'manager_name') => $entity_name,
                    'stages' => array_fill_keys($funnel_stages, 0),
                    'total_leads' => 0
                ];
            }

            $entities[$entity_name]['stages'][$stage] = $count;
            $entities[$entity_name]['total_leads'] += $count;
            $stages_summary[$stage] += $count;
            $total_leads += $count;
        }

        // Добавляем total_calls для каждой сущности
        foreach ($entities as $entity_name => &$entity_data) {
            $entity_data['total_calls'] = $total_calls_lookup[$entity_name] ?? 0;
        }
        unset($entity_data); // Разрываем ссылку

        $result = array_values($entities);
    }

    // Формируем ответ
    $response = [
        'success' => true,
        'data' => [
            'mode' => $mode,
            'items' => $result,
            'stages_summary' => $stages_summary,
            'total_leads' => $total_leads,
            'funnel_stages' => $funnel_stages
        ],
        'filters' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'departments' => $departments,
            'managers' => $managers,
            'mode' => $mode
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
