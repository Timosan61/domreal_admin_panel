<?php
/**
 * API для управления настройками LLM моделей
 * Только для администраторов
 */

session_start();
require_once '../auth/session.php';
checkAuth(true, true); // Требуется admin роль, API режим

header('Content-Type: application/json');

// Путь к .env файлу (относительно корня проекта)
$env_file_path = realpath(__DIR__ . '/../../.env');

if (!$env_file_path || !file_exists($env_file_path)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '.env file not found'
    ]);
    exit;
}

/**
 * Прочитать текущие настройки из .env файла
 */
function getCurrentSettings($env_file_path) {
    $env_content = file_get_contents($env_file_path);
    if ($env_content === false) {
        return null;
    }

    $settings = [
        'first_call' => 'cloud',  // Default values
        'repeat_call' => 'local'
    ];

    // Parse .env file
    $lines = explode("\n", $env_content);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            if ($key === 'LLM_MODE_FIRST_CALL') {
                $settings['first_call'] = strtolower($value);
            } elseif ($key === 'LLM_MODE_REPEAT_CALL') {
                $settings['repeat_call'] = strtolower($value);
            }
        }
    }

    return $settings;
}

/**
 * Обновить настройки в .env файле
 */
function updateEnvSettings($env_file_path, $new_settings) {
    // Validate settings
    $valid_modes = ['cloud', 'local'];

    if (!in_array($new_settings['first_call'], $valid_modes) ||
        !in_array($new_settings['repeat_call'], $valid_modes)) {
        return ['success' => false, 'error' => 'Invalid mode value. Use "cloud" or "local".'];
    }

    // Read current .env file
    $env_content = file_get_contents($env_file_path);
    if ($env_content === false) {
        return ['success' => false, 'error' => 'Failed to read .env file'];
    }

    $lines = explode("\n", $env_content);
    $updated_lines = [];
    $found_first = false;
    $found_repeat = false;

    // Update existing keys or mark for addition
    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Check if this line contains our keys
        if (strpos($trimmed, 'LLM_MODE_FIRST_CALL=') === 0) {
            $updated_lines[] = 'LLM_MODE_FIRST_CALL=' . $new_settings['first_call'];
            $found_first = true;
        } elseif (strpos($trimmed, 'LLM_MODE_REPEAT_CALL=') === 0) {
            $updated_lines[] = 'LLM_MODE_REPEAT_CALL=' . $new_settings['repeat_call'];
            $found_repeat = true;
        } else {
            $updated_lines[] = $line;
        }
    }

    // Add keys if they don't exist
    if (!$found_first) {
        $updated_lines[] = 'LLM_MODE_FIRST_CALL=' . $new_settings['first_call'];
    }
    if (!$found_repeat) {
        $updated_lines[] = 'LLM_MODE_REPEAT_CALL=' . $new_settings['repeat_call'];
    }

    // Write back to .env file
    $new_content = implode("\n", $updated_lines);

    // Create backup before modifying
    $backup_path = $env_file_path . '.backup-' . date('Y-m-d-H-i-s');
    copy($env_file_path, $backup_path);

    // Write new content
    if (file_put_contents($env_file_path, $new_content) === false) {
        return ['success' => false, 'error' => 'Failed to write .env file'];
    }

    // Log the change
    error_log(sprintf(
        "[LLM Settings] User %s updated LLM settings: first_call=%s, repeat_call=%s",
        $_SESSION['username'] ?? 'unknown',
        $new_settings['first_call'],
        $new_settings['repeat_call']
    ));

    return [
        'success' => true,
        'message' => 'Settings updated successfully',
        'backup_path' => basename($backup_path)
    ];
}

// Handle request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return current settings
    $settings = getCurrentSettings($env_file_path);

    if ($settings === null) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to read settings'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);

} elseif ($method === 'POST') {
    // Update settings
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['first_call']) || !isset($data['repeat_call'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid request data. Required: first_call, repeat_call'
        ]);
        exit;
    }

    $result = updateEnvSettings($env_file_path, [
        'first_call' => strtolower($data['first_call']),
        'repeat_call' => strtolower($data['repeat_call'])
    ]);

    if (!$result['success']) {
        http_response_code(500);
    }

    echo json_encode($result);

} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
