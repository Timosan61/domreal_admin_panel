<?php
/**
 * Webhook Forward Settings API
 *
 * Управление настройками переадресации вебхуков
 *
 * Endpoints:
 * - GET ?action=get - получить настройки
 * - POST ?action=update - обновить настройки
 * - POST ?action=test - протестировать переадресацию
 */

session_start();
require_once '../auth/session.php';

// Только администраторы могут управлять настройками
checkAuth($require_admin = true);

header("Content-Type: application/json; charset=UTF-8");

// Include database configuration
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Get action from query parameter
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        handleGet($db);
        break;
    case 'update':
        handleUpdate($db);
        break;
    case 'test':
        handleTest($db);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid action. Use: get, update, test"
        ], JSON_UNESCAPED_UNICODE);
        break;
}

function handleGet($db) {
    try {
        $query = "SELECT setting_key, setting_value, description
                  FROM money_tracker_settings
                  WHERE setting_key LIKE 'webhook_forward%'
                  ORDER BY setting_key";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['description']
            ];
        }

        echo json_encode([
            "success" => true,
            "settings" => $settings
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to fetch settings: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleUpdate($db) {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['enabled']) || !isset($data['url']) || !isset($data['timeout'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Missing required fields: enabled, url, timeout"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $enabled = $data['enabled'] === true || $data['enabled'] === 'true' ? 'true' : 'false';
    $url = trim($data['url']);
    $timeout = (int)$data['timeout'];

    // Validate URL if not empty
    if (!empty($url)) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Invalid URL format"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Check if URL starts with http:// or https://
        if (!preg_match('/^https?:\/\//', $url)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "URL must start with http:// or https://"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    // Validate timeout (1-60 seconds)
    if ($timeout < 1 || $timeout > 60) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Timeout must be between 1 and 60 seconds"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $db->beginTransaction();

        // Update enabled
        $query = "UPDATE money_tracker_settings
                  SET setting_value = :value, updated_at = NOW()
                  WHERE setting_key = 'webhook_forward_enabled'";
        $stmt = $db->prepare($query);
        $stmt->execute(['value' => $enabled]);

        // Update URL
        $query = "UPDATE money_tracker_settings
                  SET setting_value = :value, updated_at = NOW()
                  WHERE setting_key = 'webhook_forward_url'";
        $stmt = $db->prepare($query);
        $stmt->execute(['value' => $url]);

        // Update timeout
        $query = "UPDATE money_tracker_settings
                  SET setting_value = :value, updated_at = NOW()
                  WHERE setting_key = 'webhook_forward_timeout'";
        $stmt = $db->prepare($query);
        $stmt->execute(['value' => (string)$timeout]);

        $db->commit();

        echo json_encode([
            "success" => true,
            "message" => "Settings updated successfully"
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to update settings: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleTest($db) {
    try {
        // Get current settings
        $query = "SELECT setting_key, setting_value
                  FROM money_tracker_settings
                  WHERE setting_key IN ('webhook_forward_url', 'webhook_forward_timeout')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $url = '';
        $timeout = 10;

        foreach ($rows as $row) {
            if ($row['setting_key'] === 'webhook_forward_url') {
                $url = $row['setting_value'];
            } elseif ($row['setting_key'] === 'webhook_forward_timeout') {
                $timeout = (int)$row['setting_value'];
            }
        }

        if (empty($url)) {
            echo json_encode([
                "success" => false,
                "error" => "Forward URL is not configured"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Send test payload
        $test_payload = json_encode([
            'test' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Test forward from Domreal Whisper'
        ]);

        $start_time = microtime(true);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $test_payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $response_time = (int)((microtime(true) - $start_time) * 1000);

        curl_close($ch);

        if ($curl_error) {
            echo json_encode([
                "success" => false,
                "error" => "cURL error: " . $curl_error,
                "response_time_ms" => $response_time
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            "success" => true,
            "http_code" => $http_code,
            "response_time_ms" => $response_time,
            "response_body" => substr($response, 0, 500), // First 500 chars
            "message" => "Test completed successfully"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Test failed: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
