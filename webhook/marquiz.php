<?php
/**
 * Вебхук для Marquiz
 * URL: https://ваш-домен.ru/admin_panel/webhook/marquiz.php
 */

require_once '../config/database.php';
require_once '../lidtracker/classes/WebhookReceiver.php';

/**
 * Класс обработчика вебхуков Marquiz
 * Формат данных согласно официальной документации: https://marquiz.ru/help/integration/webhooks
 */
class MarquizReceiver extends WebhookReceiver {

    protected function extractPhone($data) {
        // Marquiz может отправлять в разных форматах

        // Формат 1: JSON - contacts.phone
        if (isset($data['contacts']['phone'])) {
            return $data['contacts']['phone'];
        }

        // Формат 2: Form-urlencoded - contacts[phone]
        // parse_str преобразует contacts[phone] → $data['contacts']['phone']
        // Поэтому проверка выше уже покрывает этот случай

        return null;
    }

    protected function mapFields($data) {
        $mapped = [];

        // Контактные данные
        // parse_str автоматически преобразует contacts[name] в $data['contacts']['name']
        $mapped['name'] = $data['contacts']['name'] ?? null;
        $mapped['email'] = $data['contacts']['email'] ?? null;

        // Quiz данные
        // quiz[id] → $data['quiz']['id']
        $mapped['external_id'] = $data['quiz']['id'] ?? null;

        // UTM метки из extra.utm или extra[utm]
        if (isset($data['extra']['utm'])) {
            $utm = $data['extra']['utm'];
            $mapped['utm_source'] = $utm['source'] ?? null;
            $mapped['utm_medium'] = $utm['medium'] ?? null;
            $mapped['utm_campaign'] = $utm['name'] ?? $utm['campaign'] ?? null;
            $mapped['utm_content'] = $utm['content'] ?? null;
            $mapped['utm_term'] = $utm['term'] ?? null;
        }

        // Дополнительные данные
        // extra[ip] → $data['extra']['ip']
        $mapped['ip_address'] = $data['extra']['ip'] ?? null;
        $mapped['referer'] = $data['extra']['referrer'] ?? $data['extra']['referer'] ?? null;
        $mapped['page_url'] = $data['extra']['href'] ?? null;

        // Новые поля (Migration 006) - Квиз и дополнительные данные
        $mapped['quiz_id'] = $data['quiz']['id'] ?? null;
        $mapped['quiz_name'] = $data['quiz']['name'] ?? null;

        // Ответы на вопросы (JSON)
        if (isset($data['answers']) && is_array($data['answers'])) {
            $mapped['quiz_answers'] = json_encode($data['answers'], JSON_UNESCAPED_UNICODE);
        } else {
            $mapped['quiz_answers'] = null;
        }

        // Результат квиза (JSON)
        if (isset($data['result'])) {
            $mapped['quiz_result'] = json_encode($data['result'], JSON_UNESCAPED_UNICODE);
        } else {
            $mapped['quiz_result'] = null;
        }

        // Дополнительные параметры из extra
        $mapped['ab_test'] = $data['extra']['ab'] ?? null;
        $mapped['timezone'] = $data['extra']['timezone'] ?? null;
        $mapped['lang'] = $data['extra']['lang'] ?? $data['extra']['lng'] ?? null;

        // Геолокация из extra
        $mapped['city'] = $data['extra']['city'] ?? null;
        $mapped['country'] = $data['extra']['country'] ?? null;

        // Cookies (JSON)
        if (isset($data['extra']['cookies']) && is_array($data['extra']['cookies'])) {
            $mapped['cookies'] = json_encode($data['extra']['cookies'], JSON_UNESCAPED_UNICODE);
        } else {
            $mapped['cookies'] = null;
        }

        // Скидка
        $mapped['discount'] = $data['extra']['discount'] ?? null;
        $mapped['discount_type'] = $data['extra']['discountType'] ?? null;

        // Roistat visit из cookies
        if (isset($data['extra']['cookies']['roistat_visit'])) {
            $mapped['roistat_visit'] = $data['extra']['cookies']['roistat_visit'];
        }

        return $mapped;
    }
}

// ============================================
// Обработка входящего запроса
// ============================================

header('Content-Type: application/json; charset=utf-8');

// Детальное логирование
$logFile = '/home/artem/Domreal_Whisper/admin_panel/webhook/marquiz_debug.log';
$logEntry = "[" . date('Y-m-d H:i:s') . "] ";
$logEntry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | ";
$logEntry .= "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . " | ";
$logEntry .= "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Получение raw payload
    $rawPayload = file_get_contents('php://input');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    error_log('[Marquiz Webhook] Content-Type: ' . $contentType);

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

    // Marquiz может отправлять данные в двух форматах:
    // 1. JSON (application/json)
    // 2. Form-urlencoded (application/x-www-form-urlencoded)

    if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        // Парсим URL-encoded данные
        parse_str($rawPayload, $data);
        error_log('[Marquiz Webhook] Parsed form-urlencoded data');
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
        error_log('[Marquiz Webhook] Parsed JSON data');
    }

    // Подключение к БД
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Обработка вебхука
    // Всегда передаём JSON (независимо от исходного формата)
    $receiver = new MarquizReceiver($db, 'marquiz');
    $leadId = $receiver->process($data, json_encode($data, JSON_UNESCAPED_UNICODE));

    // Логируем успех
    $phone = $data['contacts']['phone'] ?? 'N/A';
    $quizName = $data['quiz']['name'] ?? 'N/A';
    file_put_contents($logFile, "✅ SUCCESS - Lead ID: $leadId | Phone: $phone | Quiz: $quizName\n\n", FILE_APPEND);

    // Успешный ответ
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'message' => 'Lead received and queued for processing'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[Marquiz Webhook] Error: ' . $e->getMessage());

    // Логируем ошибку
    file_put_contents($logFile, "❌ ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
