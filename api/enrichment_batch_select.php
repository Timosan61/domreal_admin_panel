<?php
/**
 * API для получения всех ID записей batch (для выделения)
 * POST /api/enrichment_batch_select.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once '../auth/session.php';
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$batch_id = isset($input['batch_id']) ? intval($input['batch_id']) : 0;

if (!$batch_id) {
    http_response_code(400);
    echo json_encode(["error" => "batch_id required"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT id FROM client_enrichment WHERE batch_id = :batch_id ORDER BY id";
$stmt = $db->prepare($query);
$stmt->bindParam(':batch_id', $batch_id);
$stmt->execute();

$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

echo json_encode([
    'success' => true,
    'batch_id' => $batch_id,
    'ids' => $ids,
    'count' => count($ids)
], JSON_UNESCAPED_UNICODE);
?>