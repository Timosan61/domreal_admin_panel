<?php
/**
 * Вебхук для GCK
 * URL: https://ваш-домен.ru/admin_panel/webhook/gck.php
 */

require_once '../config/database.php';
require_once '../lidtracker/classes/WebhookReceiver.php';

/**
 * Класс обработчика вебхуков GCK
 * Формат данных согласно официальной документации GCK
 */
class GckReceiver extends WebhookReceiver {

    protected function extractPhone($data) {
        // GCK: phones[] - массив телефонов, берем первый
        if (isset($data['phones']) && is_array($data['phones']) && !empty($data['phones'])) {
            return $data['phones'][0];
        }
        // Fallback на прямое поле phone
        if (isset($data['phone'])) {
            return $data['phone'];
        }
        return null;
    }

    protected function mapFields($data) {
        $mapped = [];

        // Контактные данные
        $mapped['name'] = $data['name'] ?? $data['fio'] ?? null;

        // Email из массива mails[]
        if (isset($data['mails']) && is_array($data['mails']) && !empty($data['mails'])) {
            $mapped['email'] = $data['mails'][0];
        } else {
            $mapped['email'] = $data['email'] ?? null;
        }

        $mapped['external_id'] = $data['vid'] ?? null;

        // UTM метки из массива utm[]
        if (isset($data['utm']) && is_array($data['utm'])) {
            $utm = $data['utm'];
            $mapped['utm_source'] = $utm['utm_source'] ?? null;
            $mapped['utm_medium'] = $utm['utm_medium'] ?? null;
            $mapped['utm_campaign'] = $utm['utm_campaign'] ?? null;
            $mapped['utm_content'] = $utm['utm_content'] ?? null;
            $mapped['utm_term'] = $utm['utm_term'] ?? null;
        } else {
            // Fallback на корневые поля (для обратной совместимости)
            $mapped['utm_source'] = $data['utm_source'] ?? null;
            $mapped['utm_medium'] = $data['utm_medium'] ?? null;
            $mapped['utm_campaign'] = $data['utm_campaign'] ?? null;
            $mapped['utm_content'] = $data['utm_content'] ?? null;
            $mapped['utm_term'] = $data['utm_term'] ?? null;
        }

        // Дополнительные данные GCK
        $mapped['ip_address'] = $data['ip'] ?? null;
        $mapped['user_agent'] = $data['browser'] ?? null;

        // Геолокация (полная: город, регион, страна)
        $geoparts = array_filter([
            $data['city'] ?? null,
            $data['region'] ?? null,
            $data['country'] ?? null
        ]);
        $mapped['geolocation'] = !empty($geoparts) ? implode(', ', $geoparts) : null;

        $mapped['referer'] = $data['ref'] ?? $data['referer'] ?? null;
        $mapped['page_url'] = $data['page'] ?? $data['url'] ?? null;

        // Новые поля (Migration 006) - Устройство и детальная геолокация
        $mapped['browser'] = $data['browser'] ?? null;
        $mapped['device'] = $data['device'] ?? null;
        $mapped['platform'] = $data['platform'] ?? null;
        $mapped['country'] = $data['country'] ?? null;
        $mapped['region'] = $data['region'] ?? null;
        $mapped['city'] = $data['city'] ?? null;
        $mapped['site_name'] = $data['site'] ?? null;
        $mapped['roistat_visit'] = $data['roistat_visit'] ?? null;
        $mapped['client_comment'] = $data['comment'] ?? null;

        return $mapped;
    }
}

/**
 * Проверка на тестовый запрос от GCK
 */
function isTestRequest($data) {
    // Пустой payload
    if (empty($data)) {
        return true;
    }

    // Явный флаг test
    if (isset($data['test']) && $data['test'] === true) {
        return true;
    }

    // Есть vid, но нет phones и mails (признак тестового запроса)
    if (isset($data['vid']) &&
        (!isset($data['phones']) || empty($data['phones'])) &&
        (!isset($data['mails']) || empty($data['mails']))) {
        return true;
    }

    // Проверка невалидных тестовых телефонов (до сохранения в БД!)
    if (isset($data['phones']) && is_array($data['phones'])) {
        foreach ($data['phones'] as $phone) {
            $cleanPhone = preg_replace('/\D/', '', $phone);
            // Паттерны тестовых телефонов: одинаковые цифры, слишком короткие
            if (preg_match('/^(1{11}|7{11}|9{10}|0{10}|1{10})$/', $cleanPhone) ||
                strlen($cleanPhone) < 10) {
                return true;
            }
        }
    }

    // Проверка тестовых email
    if (isset($data['mails']) && is_array($data['mails'])) {
        foreach ($data['mails'] as $email) {
            if (strpos($email, 'test@') !== false ||
                strpos($email, 'hovasapyan') !== false ||
                strpos($email, 'example.com') !== false) {
                return true;
            }
        }
    }

    return false;
}

// ============================================
// Обработка входящего запроса
// ============================================

header('Content-Type: application/json; charset=utf-8');

// Детальное логирование
$logFile = '/home/artem/Domreal_Whisper/admin_panel/webhook/gck_debug.log';
$logEntry = "[" . date('Y-m-d H:i:s') . "] ";
$logEntry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | ";
$logEntry .= "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . " | ";
$logEntry .= "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Получение raw payload
    $rawPayload = file_get_contents('php://input');

    // Логируем размер payload
    file_put_contents($logFile, "Payload size: " . strlen($rawPayload) . " bytes\n", FILE_APPEND);

    // Парсинг JSON (разрешаем пустой payload для тестовых запросов)
    if (empty($rawPayload)) {
        // Тестовый запрос с пустым payload
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Test webhook received successfully',
            'note' => 'Empty payload - test request from GCK',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($rawPayload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON: ' . json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Проверка на тестовый запрос - ОТКЛЮЧЕНА
    // if (isTestRequest($data)) {
    //     http_response_code(200);
    //     echo json_encode([
    //         'success' => true,
    //         'message' => 'Test webhook received successfully',
    //         'note' => 'This is a test request from GCK',
    //         'received_data' => $data,
    //         'timestamp' => date('Y-m-d H:i:s')
    //     ], JSON_UNESCAPED_UNICODE);
    //     exit;
    // }

    // Подключение к БД
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Обработка вебхука
    $receiver = new GckReceiver($db, 'gck');
    $leadId = $receiver->process($data, $rawPayload);

    // Логируем успех
    $phone = $data['phones'][0] ?? 'N/A';
    $name = $data['name'] ?? 'N/A';
    file_put_contents($logFile, "✅ SUCCESS - Lead ID: $leadId | Phone: $phone | Name: $name\n\n", FILE_APPEND);

    // Успешный ответ
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'message' => 'Lead received and queued for processing'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[GCK Webhook] Error: ' . $e->getMessage());

    // Логируем ошибку
    file_put_contents($logFile, "❌ ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
