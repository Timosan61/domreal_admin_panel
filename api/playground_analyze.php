<?php
/**
 * API для запуска анализа звонков в Playground
 * POST /api/playground_analyze.php
 * Body: {"date": "2025-10-22", "models": ["gigachat", "openai"], "limit": 20}
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

session_start();
require_once '../auth/session.php';
checkAuth(true, true); // Only admins

// Читаем JSON из body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit();
}

$date = $data['date'] ?? date('Y-m-d');
$models = $data['models'] ?? ['gigachat', 'openai'];
$limit = $data['limit'] ?? 20;

// Валидация
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid date format"]);
    exit();
}

// Путь к Python скрипту
$script_path = '/home/artem/Domreal_Whisper/scripts/playground_analyze_batch.py';
$models_str = implode(',', $models);

// Запускаем Python скрипт в фоне
$cmd = sprintf(
    'cd /home/artem/Domreal_Whisper && /home/artem/.local/bin/poetry run python %s --date %s --limit %d --models %s > /tmp/playground_analyze_%s.log 2>&1 &',
    escapeshellarg($script_path),
    escapeshellarg($date),
    (int)$limit,
    escapeshellarg($models_str),
    date('YmdHis')
);

exec($cmd, $output, $return_code);

// Генерируем task_id для отслеживания
$task_id = 'playground_' . $date . '_' . time();

// Ответ
http_response_code(202); // Accepted
echo json_encode([
    'success' => true,
    'message' => 'Analysis started',
    'task_id' => $task_id,
    'date' => $date,
    'models' => $models,
    'limit' => $limit
]);
?>