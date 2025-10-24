<?php
/**
 * API для получения списка уникальных CRM этапов
 * GET /api/crm_stages.php
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

try {
    // Получаем уникальные комбинации воронка + этап
    $query = "SELECT DISTINCT
        ar.crm_funnel_name,
        ar.crm_step_name
    FROM analysis_results ar
    WHERE ar.crm_funnel_name IS NOT NULL
        AND ar.crm_step_name IS NOT NULL
    ORDER BY ar.crm_funnel_name, ar.crm_step_name";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Формируем ответ
    $response = [
        "success" => true,
        "data" => $stages
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to load CRM stages",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
