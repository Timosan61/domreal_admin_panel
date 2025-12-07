<?php
/**
 * API для получения деталей тревожных флагов конкретного звонка
 * GET /api/analytics/call_alert_details.php?callid=XXX
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../../auth/session.php';
checkAuth(false, true);

include_once '../../config/database.php';

$callid = isset($_GET['callid']) ? $_GET['callid'] : '';

if (empty($callid)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Параметр callid обязателен'
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Получаем информацию о звонке
    $call_query = "
        SELECT
            cr.callid,
            cr.employee_name,
            cr.client_phone,
            cr.direction,
            cr.duration_sec,
            cr.created_at,
            cr.is_first_call,
            t.text as transcript_text
        FROM calls_raw cr
        LEFT JOIN transcripts t ON cr.callid = t.callid
        WHERE cr.callid = :callid
        LIMIT 1
    ";

    $stmt = $db->prepare($call_query);
    $stmt->bindValue(':callid', $callid, PDO::PARAM_STR);
    $stmt->execute();
    $call = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$call) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Звонок не найден'
        ]);
        exit;
    }

    // Получаем все алерты для звонка
    $alerts_query = "
        SELECT
            id,
            alert_level,
            risk_category,
            scenario_code,
            scenario_name,
            confidence,
            evidence_text,
            evidence_timestamp,
            created_at
        FROM crm_alert_flags
        WHERE callid = :callid
        ORDER BY
            CASE alert_level
                WHEN 'CRITICAL' THEN 1
                WHEN 'HIGH' THEN 2
                WHEN 'MEDIUM' THEN 3
                WHEN 'LOW' THEN 4
            END,
            created_at DESC
    ";

    $stmt = $db->prepare($alerts_query);
    $stmt->bindValue(':callid', $callid, PDO::PARAM_STR);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Маппинг категорий на эмодзи
    $category_emoji = [
        'personal_contacts' => '📱',
        'offsite_meetings' => '☕',
        'bypass_procedures' => '⚠️',
        'company_criticism' => '👎',
        'switching_to_self' => '🎯',
        'hiding_information' => '🔒',
        'channel_switching' => '💬',
        'suspicious_activity' => '🔍',
        'financial_manipulation' => '💰',
        'preparation_to_leave' => '🚪'
    ];

    $category_names = [
        'personal_contacts' => 'Личные контакты',
        'offsite_meetings' => 'Встречи вне офиса',
        'bypass_procedures' => 'Обход процедур',
        'company_criticism' => 'Критика компании',
        'switching_to_self' => 'Монополизация',
        'hiding_information' => 'Скрытие информации',
        'channel_switching' => 'Смена канала связи',
        'suspicious_activity' => 'Подозрительная активность',
        'financial_manipulation' => 'Финансовые манипуляции',
        'preparation_to_leave' => 'Подготовка к уходу'
    ];

    // Добавляем эмодзи и названия
    foreach ($alerts as &$alert) {
        $alert['category_emoji'] = $category_emoji[$alert['risk_category']] ?? '🚨';
        $alert['category_name'] = $category_names[$alert['risk_category']] ?? $alert['risk_category'];

        // Эмодзи уровня
        switch ($alert['alert_level']) {
            case 'CRITICAL':
                $alert['level_emoji'] = '🔴';
                $alert['level_text'] = 'Критический';
                break;
            case 'HIGH':
                $alert['level_emoji'] = '🟠';
                $alert['level_text'] = 'Высокий';
                break;
            case 'MEDIUM':
                $alert['level_emoji'] = '🟡';
                $alert['level_text'] = 'Средний';
                break;
            case 'LOW':
                $alert['level_emoji'] = '🟢';
                $alert['level_text'] = 'Низкий';
                break;
        }

        // Форматируем уверенность
        $alert['confidence_percent'] = round($alert['confidence'] * 100);
    }

    echo json_encode([
        'success' => true,
        'call' => $call,
        'alerts' => $alerts,
        'total_alerts' => count($alerts)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
