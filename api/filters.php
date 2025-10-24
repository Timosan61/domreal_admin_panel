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
if ($_SESSION['role'] === 'admin') {
    // Админ видит все отделы
    $departments_query = "SELECT DISTINCT department FROM calls_raw WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $departments_stmt = $db->prepare($departments_query);
} else {
    // РОП видит только свои отделы
    if (empty($user_departments)) {
        $departments = [];
    } else {
        $placeholders = [];
        $params = [];
        foreach ($user_departments as $index => $dept) {
            $param_name = ':dept_' . $index;
            $placeholders[] = $param_name;
            $params[$param_name] = $dept;
        }
        $departments_query = "SELECT DISTINCT department FROM calls_raw
                              WHERE department IN (" . implode(', ', $placeholders) . ")
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

// Получаем типы звонков
$call_types_query = "SELECT DISTINCT call_type FROM analysis_results WHERE call_type IS NOT NULL AND call_type != '' ORDER BY call_type";
$call_types_stmt = $db->prepare($call_types_query);
$call_types_stmt->execute();
$call_types = $call_types_stmt->fetchAll(PDO::FETCH_COLUMN);

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
