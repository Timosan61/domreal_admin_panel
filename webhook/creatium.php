<?php
/**
 * Вебхук для Creatium
 * URL: https://ваш-домен.ru/admin_panel/webhook/creatium.php
 */

require_once '../config/database.php';
require_once '../lidtracker/classes/WebhookReceiver.php';

/**
 * Класс обработчика вебхуков Creatium
 */
class CreatiumReceiver extends WebhookReceiver {

    protected function extractPhone($data) {
        // Creatium может отправлять в двух форматах:

        // Формат 1: JSON - order.fields['Номер телефона']
        if (isset($data['order']['fields']['Номер телефона'])) {
            return $data['order']['fields']['Номер телефона'];
        }
        if (isset($data['order']['fields']['phone'])) {
            return $data['order']['fields']['phone'];
        }

        // Формат 2: Form-urlencoded - order.fields_by_name['Номер телефона']
        if (isset($data['order']['fields_by_name']['Номер телефона'])) {
            return $data['order']['fields_by_name']['Номер телефона'];
        }
        if (isset($data['order']['fields_by_name']['phone'])) {
            return $data['order']['fields_by_name']['phone'];
        }

        // Формат 3: Массив полей - ищем поле с name="Номер телефона"
        if (isset($data['order']['fields']) && is_array($data['order']['fields'])) {
            foreach ($data['order']['fields'] as $field) {
                if (isset($field['name']) &&
                    ($field['name'] === 'Номер телефона' || $field['name'] === 'phone') &&
                    isset($field['value'])) {
                    return $field['value'];
                }
            }
        }

        return null;
    }

    protected function mapFields($data) {
        $mapped = [];

        // Контактные данные
        // JSON формат
        if (isset($data['order']['fields']['Имя'])) {
            $mapped['name'] = $data['order']['fields']['Имя'];
        }
        if (isset($data['order']['fields']['Email'])) {
            $mapped['email'] = $data['order']['fields']['Email'];
        }

        // Form-urlencoded формат - fields_by_name
        if (isset($data['order']['fields_by_name']['Имя'])) {
            $mapped['name'] = $data['order']['fields_by_name']['Имя'];
        }
        if (isset($data['order']['fields_by_name']['Email'])) {
            $mapped['email'] = $data['order']['fields_by_name']['Email'];
        }

        // Массив полей
        if (isset($data['order']['fields']) && is_array($data['order']['fields'])) {
            foreach ($data['order']['fields'] as $field) {
                if (isset($field['name']) && isset($field['value'])) {
                    if ($field['name'] === 'Имя') {
                        $mapped['name'] = $field['value'];
                    }
                    if ($field['name'] === 'Email') {
                        $mapped['email'] = $field['value'];
                    }
                }
            }
        }

        // UTM метки (могут быть в visit или в order.fields_by_name)
        $mapped['utm_source'] = $data['order']['utm_source'] ??
                                $data['order']['fields_by_name']['utm_source'] ??
                                $data['visit']['utm_source'] ?? null;
        $mapped['utm_medium'] = $data['order']['utm_medium'] ??
                                $data['order']['fields_by_name']['utm_medium'] ??
                                $data['visit']['utm_medium'] ?? null;
        $mapped['utm_campaign'] = $data['order']['utm_campaign'] ??
                                  $data['order']['fields_by_name']['utm_campaign'] ??
                                  $data['visit']['utm_campaign'] ?? null;
        $mapped['utm_content'] = $data['order']['utm_content'] ??
                                 $data['order']['fields_by_name']['utm_content'] ??
                                 $data['visit']['utm_content'] ?? null;
        $mapped['utm_term'] = $data['order']['utm_term'] ??
                              $data['order']['fields_by_name']['utm_term'] ??
                              $data['visit']['utm_term'] ?? null;

        // Дополнительные данные
        $mapped['ip_address'] = $data['visit']['ip'] ?? null;
        $mapped['user_agent'] = $data['visit']['user_agent'] ?? null;

        // Геолокация может быть строкой или массивом
        if (isset($data['visit']['geolocation'])) {
            if (is_array($data['visit']['geolocation'])) {
                $mapped['geolocation'] = isset($data['visit']['geolocation']['city'])
                    ? $data['visit']['geolocation']['city'] . ', ' . ($data['visit']['geolocation']['country'] ?? '')
                    : null;
                // Детальная геолокация (новые поля)
                $mapped['country'] = $data['visit']['geolocation']['country'] ?? null;
                $mapped['region'] = $data['visit']['geolocation']['region'] ?? null;
                $mapped['city'] = $data['visit']['geolocation']['city'] ?? null;
            } else {
                $mapped['geolocation'] = $data['visit']['geolocation'];
                $mapped['country'] = null;
                $mapped['region'] = null;
                $mapped['city'] = null;
            }
        } else {
            $mapped['geolocation'] = null;
            $mapped['country'] = null;
            $mapped['region'] = null;
            $mapped['city'] = null;
        }

        $mapped['referer'] = $data['visit']['referer'] ?? null;
        $mapped['page_url'] = $data['page']['url'] ?? null;

        // Новые поля (Migration 006) - Информация о сайте и форме
        $mapped['site_name'] = $data['site']['name'] ?? null;
        $mapped['site_url'] = $data['site']['url'] ?? null;
        $mapped['page_name'] = $data['page']['name'] ?? null;
        $mapped['form_name'] = $data['order']['form_name'] ??
                               $data['order']['fields_by_name']['form_name'] ?? null;

        return $mapped;
    }
}

// ============================================
// Обработка входящего запроса
// ============================================

header('Content-Type: application/json; charset=utf-8');

// Детальное логирование (для отладки)
$logFile = '/home/artem/Domreal_Whisper/admin_panel/webhook/creatium_debug.log';
$logEntry = "[" . date('Y-m-d H:i:s') . "] ";
$logEntry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | ";
$logEntry .= "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . " | ";
$logEntry .= "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Получение raw payload
    $rawPayload = file_get_contents('php://input');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    error_log('[Creatium Webhook] Content-Type: ' . $contentType);

    // Логируем размер payload
    file_put_contents($logFile, "Payload size: " . strlen($rawPayload) . " bytes\n", FILE_APPEND);

    if (empty($rawPayload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Empty payload'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Creatium может отправлять данные в двух форматах:
    // 1. JSON (application/json)
    // 2. Form-urlencoded (application/x-www-form-urlencoded)

    if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        // Парсим URL-encoded данные
        parse_str($rawPayload, $data);
        error_log('[Creatium Webhook] Parsed form-urlencoded data');
    } else {
        // Парсим JSON
        $data = json_decode($rawPayload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        error_log('[Creatium Webhook] Parsed JSON data');
    }

    // Подключение к БД
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Обработка вебхука
    // Всегда передаём JSON (независимо от исходного формата)
    $receiver = new CreatiumReceiver($db, 'creatium');
    $leadId = $receiver->process($data, json_encode($data, JSON_UNESCAPED_UNICODE));

    // Логируем успех
    file_put_contents($logFile, "✅ SUCCESS - Lead ID: $leadId | Phone: " . ($data['order']['fields_by_name']['Номер телефона'] ?? 'N/A') . " | Name: " . ($data['order']['fields_by_name']['Имя'] ?? 'N/A') . "\n\n", FILE_APPEND);

    // Успешный ответ
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'message' => 'Lead received and queued for processing'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[Creatium Webhook] Error: ' . $e->getMessage());

    // Логируем ошибку
    file_put_contents($logFile, "❌ ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
