<?php
/**
 * API для получения списка отделов из таблицы employees
 * GET /admin/api/departments.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

session_start();
require_once '../../auth/session.php';
checkAuth(); // Доступно всем авторизованным пользователям

include_once '../../config/database.php';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем список всех отделов из таблицы employees
$query = "SELECT DISTINCT department
          FROM employees
          WHERE department IS NOT NULL AND department != ''
          ORDER BY department";

$stmt = $db->prepare($query);
$stmt->execute();

$departments = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $departments[] = [
        'value' => $row['department'],
        'label' => $row['department']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $departments,
    'total' => count($departments)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
