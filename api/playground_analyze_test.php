<?php
/**
 * API для запуска анализа звонков в Playground
 * POST /api/playground_analyze.php
 * Body: {"date": "2025-10-22", "models": ["gigachat", "openai"], "limit": 20}
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// TEST VERSION - NO AUTH
session_start();
$_SESSION['username'] = 'test_admin';
$_SESSION['role'] = 'admin';

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
$project_root = '/home/artem/Domreal_Whisper';
$script_path = $project_root . '/scripts/playground_analyze_batch.py';
$poetry_path = '/home/artem/.local/bin/poetry';

// Проверяем, что файлы существуют
if (!file_exists($script_path)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Python скрипт не найден: ' . $script_path
    ]);
    exit();
}

if (!file_exists($poetry_path)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Poetry не найден. Установите Poetry для запуска анализа.'
    ]);
    exit();
}

// Проверяем .env файл и API ключи
$env_file = $project_root . '/.env';
if (!file_exists($env_file)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Файл .env не найден. Требуются API ключи для LLM.'
    ]);
    exit();
}

$models_str = implode(',', $models);
$log_file = '/tmp/playground_analyze_' . date('YmdHis') . '.log';

// Запускаем Python скрипт в фоне
$cmd = sprintf(
    'cd %s && %s run python %s --date %s --limit %d --models %s > %s 2>&1 &',
    escapeshellarg($project_root),
    escapeshellarg($poetry_path),
    escapeshellarg($script_path),
    escapeshellarg($date),
    (int)$limit,
    escapeshellarg($models_str),
    escapeshellarg($log_file)
);

exec($cmd, $output, $return_code);

// Генерируем task_id для отслеживания
$task_id = 'playground_' . $date . '_' . time();

// Ждем 2 секунды и проверяем, не упал ли скрипт сразу
sleep(2);
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);

    // Проверяем на типичные ошибки
    if (strpos($log_content, 'Payment Required') !== false || strpos($log_content, '402') !== false) {
        http_response_code(402);
        echo json_encode([
            'success' => false,
            'error' => '💳 Недостаточно средств на балансе GigaChat. Попробуйте только OpenAI или пополните баланс.'
        ]);
        exit();
    }

    if (strpos($log_content, 'Rate limit') !== false || strpos($log_content, '429') !== false) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => '⏱️ Превышен лимит запросов к API. Попробуйте позже или уменьшите количество звонков.'
        ]);
        exit();
    }

    if (strpos($log_content, 'Connection') !== false || strpos($log_content, 'timeout') !== false) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => '🔌 Проблемы с подключением к LLM API. Проверьте интернет-соединение.'
        ]);
        exit();
    }

    if (strpos($log_content, 'Unauthorized') !== false || strpos($log_content, '401') !== false) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => '🔑 Неверный API ключ. Проверьте настройки в файле .env'
        ]);
        exit();
    }
}

// Ответ
http_response_code(202); // Accepted
echo json_encode([
    'success' => true,
    'message' => 'Analysis started',
    'task_id' => $task_id,
    'date' => $date,
    'models' => $models,
    'limit' => $limit,
    'log_file' => $log_file
]);
?>