<?php
/**
 * API для получения доступных значений фильтров
 * GET /api/filters.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем список отделов, доступных пользователю
$user_departments = getUserDepartments();

// Фильтр по отделам в зависимости от роли
// ВАЖНО: Показываем только отделы с активностью за последние 7 дней (агрессивная фильтрация неактивных)
$active_date_threshold = date('Y-m-d H:i:s', strtotime('-7 days'));

if ($_SESSION['role'] === 'admin') {
    // Админ видит только активные отделы (звонки за последние 7 дней)
    $departments_query = "SELECT DISTINCT department
                          FROM calls_raw
                          WHERE department IS NOT NULL
                            AND department != ''
                            AND started_at_utc >= :active_threshold
                          ORDER BY department";
    $departments_stmt = $db->prepare($departments_query);
    $departments_stmt->bindValue(':active_threshold', $active_date_threshold);
} else {
    // РОП видит только свои активные отделы
    if (empty($user_departments)) {
        $departments = [];
    } else {
        $placeholders = [];
        $params = [':active_threshold' => $active_date_threshold];
        foreach ($user_departments as $index => $dept) {
            $param_name = ':dept_' . $index;
            $placeholders[] = $param_name;
            $params[$param_name] = $dept;
        }
        $departments_query = "SELECT DISTINCT department
                              FROM calls_raw
                              WHERE department IN (" . implode(', ', $placeholders) . ")
                                AND started_at_utc >= :active_threshold
                              ORDER BY department";
        $departments_stmt = $db->prepare($departments_query);
        foreach ($params as $key => $value) {
            $departments_stmt->bindValue($key, $value);
        }
    }
}

if (!empty($user_departments) || $_SESSION['role'] === 'admin') {
    $departments_stmt->execute();
    $departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $departments = [];
}

// Получаем список менеджеров с учетом прав доступа
// Поддержка фильтрации по выбранным отделам через GET параметр 'departments' (CSV)
$departments_filter = isset($_GET['departments']) ? $_GET['departments'] : '';

// Определяем отделы для фильтрации менеджеров
$allowed_departments = [];
if (!empty($departments_filter)) {
    // Пользователь выбрал конкретные отделы в фильтре
    $selected_depts = explode(',', $departments_filter);

    // Для РОП - пересечение выбранных и доступных отделов
    if ($_SESSION['role'] !== 'admin') {
        $allowed_departments = array_intersect($selected_depts, $user_departments);
    } else {
        $allowed_departments = $selected_depts;
    }
} else {
    // Фильтр отделов не выбран - используем все доступные отделы пользователя
    $allowed_departments = $user_departments;
}

// Загружаем менеджеров только из разрешенных отделов
if (empty($allowed_departments)) {
    $managers = [];
} else {
    $placeholders = [];
    $params = [];
    foreach ($allowed_departments as $index => $dept) {
        $param_name = ':mgr_dept_' . $index;
        $placeholders[] = $param_name;
        $params[$param_name] = $dept;
    }

    $managers_query = "SELECT DISTINCT employee_name FROM calls_raw
                       WHERE employee_name IS NOT NULL AND employee_name != ''
                       AND department IN (" . implode(', ', $placeholders) . ")
                       ORDER BY employee_name";
    $managers_stmt = $db->prepare($managers_query);
    foreach ($params as $key => $value) {
        $managers_stmt->bindValue($key, $value);
    }
    $managers_stmt->execute();
    $managers = $managers_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Типы звонков (статический массив - 3 типа)
// Не используем запрос к БД, т.к. типы определяются на лету по is_first_call + duration_sec
$call_types = ['first_call', 'repeat_call', 'failed_call'];

// Формируем ответ
$response = [
    "success" => true,
    "data" => [
        "departments" => $departments,
        "managers" => $managers,
        "call_types" => $call_types
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
