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

include_once '../config/database.php';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем список отделов
$departments_query = "SELECT DISTINCT department FROM calls_raw WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем список менеджеров (с фильтрацией по отделу, если указан)
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

if (!empty($department_filter)) {
    // Фильтруем менеджеров по отделу
    $managers_query = "SELECT DISTINCT employee_name FROM calls_raw
                       WHERE employee_name IS NOT NULL AND employee_name != ''
                       AND department = :department
                       ORDER BY employee_name";
    $managers_stmt = $db->prepare($managers_query);
    $managers_stmt->bindParam(':department', $department_filter);
} else {
    // Возвращаем всех менеджеров
    $managers_query = "SELECT DISTINCT employee_name FROM calls_raw
                       WHERE employee_name IS NOT NULL AND employee_name != ''
                       ORDER BY employee_name";
    $managers_stmt = $db->prepare($managers_query);
}

$managers_stmt->execute();
$managers = $managers_stmt->fetchAll(PDO::FETCH_COLUMN);

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
