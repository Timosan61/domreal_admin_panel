<?php
/**
 * API для массовых операций с записями обогащения
 * POST /api/enrichment_bulk_action.php
 *
 * ВАЖНО: Доступ только для администраторов!
 *
 * Параметры POST:
 * - action (string) - тип действия: "export" или "delete"
 * - ids (array) - массив ID записей
 *
 * Для action="export":
 *   Перенаправляет на enrichment_export_xlsx.php с передачей selected_ids
 *
 * Для action="delete":
 *   Удаляет выбранные записи и возвращает JSON:
 *   {
 *     "success": true,
 *     "deleted": 10,
 *     "message": "Удалено 10 записей"
 *   }
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';

// КРИТИЧЕСКИ ВАЖНО: Проверка админских прав (API режим)
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

// Получаем данные из POST
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || empty($input['action'])) {
    http_response_code(400);
    echo json_encode(["error" => "Параметр 'action' обязателен"]);
    exit();
}

if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
    http_response_code(400);
    echo json_encode(["error" => "Параметр 'ids' обязателен и должен быть непустым массивом"]);
    exit();
}

$action = $input['action'];
$ids = array_map('intval', $input['ids']);

// ======================
// ДЕЙСТВИЕ: ЭКСПОРТ
// ======================
if ($action === 'export') {
    // Для экспорта возвращаем URL для скачивания
    // Frontend должен отправить POST запрос на enrichment_export_xlsx.php с selected_ids
    echo json_encode([
        "success" => true,
        "action" => "export",
        "message" => "Подготовлено для экспорта",
        "ids" => $ids,
        "count" => count($ids),
    ]);
    exit();
}

// ======================
// ДЕЙСТВИЕ: УДАЛЕНИЕ
// ======================
if ($action === 'delete') {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        http_response_code(503);
        echo json_encode(["error" => "Database connection failed"]);
        exit();
    }

    // Подготовка плейсхолдеров для IN (...)
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';

    $delete_query = "DELETE FROM client_enrichment WHERE id IN ($placeholders)";

    try {
        $stmt = $db->prepare($delete_query);
        $stmt->execute($ids);

        $deleted = $stmt->rowCount();

        echo json_encode([
            "success" => true,
            "deleted" => $deleted,
            "message" => "Удалено записей: $deleted",
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "error" => "Ошибка при удалении: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit();
}

// Неизвестное действие
http_response_code(400);
echo json_encode([
    "error" => "Неизвестное действие. Доступны: 'export', 'delete'"
], JSON_UNESCAPED_UNICODE);
