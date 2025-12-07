<?php
/**
 * API для получения тревожных флагов конфликта интересов по списку звонков
 * GET /api/alert_flags.php?callids=call1,call2,call3
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Получаем список callids
$callids_param = isset($_GET['callids']) ? $_GET['callids'] : '';

if (empty($callids_param)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing callids parameter']);
    exit;
}

// Разбираем список callids
$callids = array_filter(array_map('trim', explode(',', $callids_param)));

if (empty($callids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty callids list']);
    exit;
}

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

try {
    // Создаем плейсхолдеры для IN clause
    $placeholders = implode(',', array_fill(0, count($callids), '?'));

    // SQL запрос для получения агрегированных флагов по звонкам
    $query = "
        SELECT
            callid,
            COUNT(*) as total_flags,
            SUM(CASE WHEN alert_level = 'LOW' THEN 1 ELSE 0 END) as low_flags,
            SUM(CASE WHEN alert_level = 'MEDIUM' THEN 1 ELSE 0 END) as medium_flags,
            SUM(CASE WHEN alert_level = 'HIGH' THEN 1 ELSE 0 END) as high_flags,
            SUM(CASE WHEN alert_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_flags,
            MAX(CASE
                WHEN alert_level = 'CRITICAL' THEN 4
                WHEN alert_level = 'HIGH' THEN 3
                WHEN alert_level = 'MEDIUM' THEN 2
                WHEN alert_level = 'LOW' THEN 1
                ELSE 0
            END) as max_level_num,
            MAX(alert_level) as max_alert_level,
            GROUP_CONCAT(DISTINCT risk_category ORDER BY risk_category SEPARATOR ', ') as risk_categories,
            GROUP_CONCAT(DISTINCT scenario_name ORDER BY scenario_name SEPARATOR ' | ') as scenarios
        FROM crm_alert_flags
        WHERE callid IN ($placeholders)
        GROUP BY callid
    ";

    $stmt = $db->prepare($query);

    // Привязываем параметры
    foreach ($callids as $index => $callid) {
        $stmt->bindValue($index + 1, $callid, PDO::PARAM_STR);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Преобразуем результаты в ассоциативный массив callid => данные
    $flags_by_callid = [];
    foreach ($results as $row) {
        // Определяем финальный уровень тревоги
        $alert_level = 'NONE';
        if ($row['critical_flags'] > 0) {
            $alert_level = 'CRITICAL';
        } elseif ($row['high_flags'] > 0) {
            $alert_level = 'HIGH';
        } elseif ($row['medium_flags'] > 0) {
            $alert_level = 'MEDIUM';
        } elseif ($row['low_flags'] > 0) {
            $alert_level = 'LOW';
        }

        $flags_by_callid[$row['callid']] = [
            'total_flags' => (int)$row['total_flags'],
            'alert_level' => $alert_level,
            'low_flags' => (int)$row['low_flags'],
            'medium_flags' => (int)$row['medium_flags'],
            'high_flags' => (int)$row['high_flags'],
            'critical_flags' => (int)$row['critical_flags'],
            'risk_categories' => $row['risk_categories'],
            'scenarios' => $row['scenarios']
        ];
    }

    // Для звонков без флагов возвращаем пустые данные
    foreach ($callids as $callid) {
        if (!isset($flags_by_callid[$callid])) {
            $flags_by_callid[$callid] = [
                'total_flags' => 0,
                'alert_level' => 'NONE',
                'low_flags' => 0,
                'medium_flags' => 0,
                'high_flags' => 0,
                'critical_flags' => 0,
                'risk_categories' => null,
                'scenarios' => null
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $flags_by_callid
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
