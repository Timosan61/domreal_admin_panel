<?php
/**
 * API для анализа рисков конфликта интересов по менеджерам
 * GET /api/analytics/manager_risk_analysis.php
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../../config/database.php';

// Получаем параметры фильтрации
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // Начало текущего месяца по умолчанию
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Сегодня по умолчанию
$department = isset($_GET['department']) ? $_GET['department'] : '';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

try {
    // SQL запрос для получения статистики по менеджерам
    $query = "
        SELECT
            cr.employee_name as manager_name,
            MAX(cr.account_login) as manager_id,
            GROUP_CONCAT(DISTINCT cr.department SEPARATOR ', ') as department,
            COUNT(DISTINCT af.callid) as calls_with_alerts,
            COUNT(DISTINCT cr.callid) as total_calls,
            SUM(CASE WHEN af.alert_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_alerts,
            SUM(CASE WHEN af.alert_level = 'HIGH' THEN 1 ELSE 0 END) as high_alerts,
            SUM(CASE WHEN af.alert_level = 'MEDIUM' THEN 1 ELSE 0 END) as medium_alerts,
            SUM(CASE WHEN af.alert_level = 'LOW' THEN 1 ELSE 0 END) as low_alerts,
            COUNT(af.id) as total_flags,

            -- Топ-3 категории рисков
            (
                SELECT GROUP_CONCAT(DISTINCT risk_category ORDER BY cnt DESC SEPARATOR ', ')
                FROM (
                    SELECT risk_category, COUNT(*) as cnt
                    FROM crm_alert_flags af2
                    JOIN calls_raw cr2 ON af2.callid = cr2.callid
                    WHERE cr2.employee_name = cr.employee_name
                        AND cr2.created_at >= ?
                        AND cr2.created_at <= ?
                    GROUP BY risk_category
                    ORDER BY cnt DESC
                    LIMIT 3
                ) top_risks
            ) as top_risk_categories,

            -- Расчет риск-скора (0-100)
            LEAST(100, GREATEST(0,
                (SUM(CASE WHEN af.alert_level = 'CRITICAL' THEN 10 ELSE 0 END) +
                 SUM(CASE WHEN af.alert_level = 'HIGH' THEN 5 ELSE 0 END) +
                 SUM(CASE WHEN af.alert_level = 'MEDIUM' THEN 2 ELSE 0 END) +
                 SUM(CASE WHEN af.alert_level = 'LOW' THEN 1 ELSE 0 END))
            )) as risk_score,

            MAX(af.call_date) as last_alert_date,
            MIN(af.call_date) as first_alert_date

        FROM calls_raw cr
        LEFT JOIN crm_alert_flags af ON cr.callid = af.callid
        WHERE cr.created_at >= ?
            AND cr.created_at <= ?
            " . ($department ? "AND cr.department = ?" : "") . "
        GROUP BY cr.employee_name
        HAVING total_flags > 0
        ORDER BY risk_score DESC, total_flags DESC
    ";

    $stmt = $db->prepare($query);

    // Параметры для подзапроса top_risk_categories
    $stmt->bindValue(1, $date_from . ' 00:00:00', PDO::PARAM_STR);
    $stmt->bindValue(2, $date_to . ' 23:59:59', PDO::PARAM_STR);

    // Параметры для основного запроса
    $stmt->bindValue(3, $date_from . ' 00:00:00', PDO::PARAM_STR);
    $stmt->bindValue(4, $date_to . ' 23:59:59', PDO::PARAM_STR);

    if ($department) {
        $stmt->bindValue(5, $department, PDO::PARAM_STR);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Определяем уровень риска менеджера
    foreach ($results as &$row) {
        $risk_score = (int)$row['risk_score'];

        if ($risk_score >= 50) {
            $row['risk_level'] = 'CRITICAL';
            $row['risk_level_text'] = 'Критический';
            $row['risk_level_emoji'] = '🔴';
        } elseif ($risk_score >= 30) {
            $row['risk_level'] = 'HIGH';
            $row['risk_level_text'] = 'Высокий';
            $row['risk_level_emoji'] = '🟠';
        } elseif ($risk_score >= 15) {
            $row['risk_level'] = 'MEDIUM';
            $row['risk_level_text'] = 'Средний';
            $row['risk_level_emoji'] = '🟡';
        } else {
            $row['risk_level'] = 'LOW';
            $row['risk_level_text'] = 'Низкий';
            $row['risk_level_emoji'] = '🟢';
        }

        // Процент звонков с алертами
        $row['alert_percentage'] = $row['total_calls'] > 0
            ? round(($row['calls_with_alerts'] / $row['total_calls']) * 100, 1)
            : 0;
    }

    // Статистика по всем менеджерам
    $total_managers_with_alerts = count($results);
    $total_alerts = array_sum(array_column($results, 'total_flags'));
    $critical_count = count(array_filter($results, function($r) { return $r['risk_level'] === 'CRITICAL'; }));
    $high_count = count(array_filter($results, function($r) { return $r['risk_level'] === 'HIGH'; }));

    echo json_encode([
        'success' => true,
        'data' => $results,
        'summary' => [
            'total_managers_with_alerts' => $total_managers_with_alerts,
            'total_alerts' => $total_alerts,
            'critical_managers' => $critical_count,
            'high_risk_managers' => $high_count,
            'period_from' => $date_from,
            'period_to' => $date_to
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
