<?php
/**
 * API для получения звонков менеджера с тревожными флагами
 * GET /api/analytics/manager_calls_with_alerts.php?manager_name=XXX&date_from=XXX&date_to=XXX
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

// Получаем параметры
$manager_name = isset($_GET['manager_name']) ? $_GET['manager_name'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

if (empty($manager_name)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Параметр manager_name обязателен'
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "
        SELECT
            cr.callid,
            cr.employee_name,
            cr.client_phone,
            cr.direction,
            cr.duration_sec,
            cr.created_at as call_date,
            cr.is_first_call,
            COUNT(af.id) as total_alerts,
            SUM(CASE WHEN af.alert_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_count,
            SUM(CASE WHEN af.alert_level = 'HIGH' THEN 1 ELSE 0 END) as high_count,
            SUM(CASE WHEN af.alert_level = 'MEDIUM' THEN 1 ELSE 0 END) as medium_count,
            SUM(CASE WHEN af.alert_level = 'LOW' THEN 1 ELSE 0 END) as low_count,
            GROUP_CONCAT(DISTINCT af.risk_category SEPARATOR ', ') as risk_categories,
            MAX(CASE
                WHEN af.alert_level = 'CRITICAL' THEN 4
                WHEN af.alert_level = 'HIGH' THEN 3
                WHEN af.alert_level = 'MEDIUM' THEN 2
                WHEN af.alert_level = 'LOW' THEN 1
                ELSE 0
            END) as max_alert_level_num
        FROM calls_raw cr
        INNER JOIN crm_alert_flags af ON cr.callid = af.callid
        WHERE cr.employee_name = :manager_name
            AND cr.created_at >= :date_from
            AND cr.created_at <= :date_to
        GROUP BY cr.callid, cr.employee_name, cr.client_phone, cr.direction, cr.duration_sec, cr.created_at, cr.is_first_call
        ORDER BY max_alert_level_num DESC, total_alerts DESC, cr.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':manager_name', $manager_name, PDO::PARAM_STR);
    $stmt->bindValue(':date_from', $date_from . ' 00:00:00', PDO::PARAM_STR);
    $stmt->bindValue(':date_to', $date_to . ' 23:59:59', PDO::PARAM_STR);
    $stmt->execute();

    $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Определяем максимальный уровень алерта для каждого звонка
    foreach ($calls as &$call) {
        if ($call['critical_count'] > 0) {
            $call['max_alert_level'] = 'CRITICAL';
            $call['alert_badge'] = '🔴';
        } elseif ($call['high_count'] > 0) {
            $call['max_alert_level'] = 'HIGH';
            $call['alert_badge'] = '🟠';
        } elseif ($call['medium_count'] > 0) {
            $call['max_alert_level'] = 'MEDIUM';
            $call['alert_badge'] = '🟡';
        } else {
            $call['max_alert_level'] = 'LOW';
            $call['alert_badge'] = '🟢';
        }

        // Форматируем дату
        $call['call_date_formatted'] = date('d.m.Y H:i', strtotime($call['call_date']));

        // Форматируем длительность
        $call['duration_formatted'] = gmdate('i:s', $call['duration_sec']);
    }

    echo json_encode([
        'success' => true,
        'manager_name' => $manager_name,
        'total_calls' => count($calls),
        'calls' => $calls
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
