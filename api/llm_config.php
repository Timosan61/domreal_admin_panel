<?php
/**
 * LLM Configuration API
 * Управление режимами работы LLM (cloud/hybrid/local)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Путь к .env файлу
$envFile = __DIR__ . '/../../.env';

/**
 * Получить текущую конфигурацию LLM
 */
function getCurrentConfig($envFile) {
    if (!file_exists($envFile)) {
        return [
            'error' => 'Config file not found',
            'file' => $envFile
        ];
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [
        'llm_mode' => 'hybrid', // default
        'use_local_llm' => false,
        'use_unified_analysis' => false,
        'gpu_management_enabled' => true,
        'local_llm_model_path' => '',
        'local_llm_n_gpu_layers' => 40,
        'llm_provider' => 'gigachat'
    ];

    foreach ($lines as $line) {
        // Пропускаем комментарии и пустые строки
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }

        // Парсим KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            switch ($key) {
                case 'LLM_MODE':
                    $config['llm_mode'] = $value;
                    break;
                case 'USE_LOCAL_LLM':
                    $config['use_local_llm'] = ($value === 'true');
                    break;
                case 'USE_UNIFIED_ANALYSIS':
                    $config['use_unified_analysis'] = ($value === 'true');
                    break;
                case 'GPU_MANAGEMENT_ENABLED':
                    $config['gpu_management_enabled'] = ($value === 'true');
                    break;
                case 'LOCAL_LLM_MODEL_PATH':
                    $config['local_llm_model_path'] = $value;
                    break;
                case 'LOCAL_LLM_N_GPU_LAYERS':
                    $config['local_llm_n_gpu_layers'] = (int)$value;
                    break;
                case 'LLM_PROVIDER':
                    $config['llm_provider'] = $value;
                    break;
            }
        }
    }

    return $config;
}

/**
 * Обновить LLM_MODE в .env
 */
function updateLlmMode($envFile, $newMode) {
    if (!file_exists($envFile)) {
        return [
            'success' => false,
            'error' => 'Config file not found'
        ];
    }

    // Валидация режима
    $validModes = ['cloud', 'hybrid', 'local'];
    if (!in_array($newMode, $validModes)) {
        return [
            'success' => false,
            'error' => 'Invalid mode. Use: cloud, hybrid, or local'
        ];
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    $updated = false;

    foreach ($lines as &$line) {
        if (strpos($line, 'LLM_MODE=') === 0) {
            $line = "LLM_MODE=$newMode";
            $updated = true;
            break;
        }
    }

    // Если LLM_MODE не найден, добавляем в конец
    if (!$updated) {
        $lines[] = "LLM_MODE=$newMode";
    }

    // Сохраняем файл
    if (file_put_contents($envFile, implode(PHP_EOL, $lines) . PHP_EOL)) {
        return [
            'success' => true,
            'mode' => $newMode,
            'message' => 'LLM mode updated successfully',
            'restart_required' => true
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Failed to write config file'
        ];
    }
}

/**
 * Получить статус worker
 */
function getWorkerStatus() {
    // Проверяем статус systemd worker
    $output = [];
    $returnVar = 0;
    exec('systemctl is-active domreal-worker-analyze 2>/dev/null', $output, $returnVar);

    $isRunning = ($returnVar === 0 && isset($output[0]) && $output[0] === 'active');

    return [
        'worker' => 'domreal-worker-analyze',
        'is_running' => $isRunning,
        'status' => $isRunning ? 'active' : 'inactive'
    ];
}

// Обработка запросов
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // GET - получить текущую конфигурацию
        $config = getCurrentConfig($envFile);
        $worker = getWorkerStatus();

        echo json_encode([
            'success' => true,
            'config' => $config,
            'worker' => $worker
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } elseif ($method === 'POST') {
        // POST - обновить LLM_MODE
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['mode'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing "mode" parameter'
            ]);
            exit;
        }

        $result = updateLlmMode($envFile, $input['mode']);

        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }

        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
