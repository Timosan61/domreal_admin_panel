<?php
/**
 * API для ручного запуска worker обогащения
 * POST /api/enrichment_trigger_worker.php
 *
 * Параметры:
 * - batch_size (optional, default: 50)
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once '../auth/session.php';
checkAuth($require_admin = true, $is_api = true);

// Получаем параметры
$input = json_decode(file_get_contents('php://input'), true);
$batch_size = isset($input['batch_size']) ? min(100, max(10, intval($input['batch_size']))) : 50;

// Путь к проекту и Python
$project_path = '/home/artem/Domreal_Whisper';
$python_path = '/home/artem/.cache/pypoetry/virtualenvs/domreal-whisper-rKzriwEo-py3.11/bin/python';

// Проверяем существование
if (!file_exists($python_path)) {
    http_response_code(500);
    echo json_encode([
        "error" => "Python not found at $python_path"
    ]);
    exit();
}

// Создаём временный скрипт для запуска
$temp_script = tempnam(sys_get_temp_dir(), 'enrichment_');
file_put_contents($temp_script, <<<PYTHON
import asyncio
import sys
sys.path.insert(0, '$project_path')

from workers.worker_enrichment import EnrichmentWorker

async def process_once():
    worker = EnrichmentWorker(max_workers=3, batch_size=$batch_size, poll_interval=1)
    processed = await worker.process_batch()
    if processed > 0:
        worker.update_batch_counters()
    print(f'Обработано: {processed}')
    return processed

result = asyncio.run(process_once())
sys.exit(0 if result > 0 else 1)
PYTHON
);

// Запускаем в фоне
$log_file = '/tmp/enrichment_manual_trigger.log';
$cmd = "cd $project_path && nohup $python_path $temp_script >> $log_file 2>&1 &";
$output = shell_exec($cmd);

// Даем время на запуск
usleep(100000); // 0.1 сек

// Проверяем, что процесс запустился
$check = shell_exec("ps aux | grep -v grep | grep 'enrichment_' | wc -l");
$is_running = intval(trim($check)) > 0;

// Удаляем временный файл через 5 секунд
exec("(sleep 5 && rm -f $temp_script) > /dev/null 2>&1 &");

echo json_encode([
    "success" => true,
    "triggered" => true,
    "batch_size" => $batch_size,
    "process_started" => $is_running,
    "log_file" => $log_file,
    "message" => $is_running
        ? "Worker запущен для обработки до $batch_size номеров"
        : "Worker запущен в фоне (проверьте лог: $log_file)"
], JSON_UNESCAPED_UNICODE);
