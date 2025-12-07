<?php
/**
 * API: Тренды конверсии по дням
 *
 * Возвращает временной ряд конверсии (общая, в сделки, в горячие)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Получение фильтров
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $departments = $_GET['departments'] ?? null;
    $managers = $_GET['managers'] ?? null;

    // Построение WHERE условий
    $where_conditions = [
        "ar.call_date BETWEEN :date_from AND :date_to",
        "ar.real_conversation = 1",
        "ar.is_work_related = 1"
    ];
    $params = [
        ':date_from' => $date_from . ' 00:00:00',
        ':date_to' => $date_to . ' 23:59:59'
    ];

    if ($departments) {
        $dept_list = explode(',', $departments);
        $dept_placeholders = [];
        foreach ($dept_list as $i => $dept) {
            $key = ":dept_$i";
            $dept_placeholders[] = $key;
            $params[$key] = trim($dept);
        }
        $where_conditions[] = "ar.employee_department IN (" . implode(',', $dept_placeholders) . ")";
    }

    if ($managers) {
        $mgr_list = explode(',', $managers);
        $mgr_placeholders = [];
        foreach ($mgr_list as $i => $mgr) {
            $key = ":mgr_$i";
            $mgr_placeholders[] = $key;
            $params[$key] = trim($mgr);
        }
        $where_conditions[] = "ar.employee_full_name IN (" . implode(',', $mgr_placeholders) . ")";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Запрос для трендов конверсии
    $query = "
        SELECT
            DATE(ar.call_date) AS call_day,
            COUNT(*) AS total_calls,
            SUM(CASE WHEN ar.is_successful = 1 THEN 1 ELSE 0 END) AS successful_calls,
            SUM(CASE WHEN ar.deal_status IS NOT NULL THEN 1 ELSE 0 END) AS deals,
            SUM(CASE WHEN ar.deal_status = 'Горячий' THEN 1 ELSE 0 END) AS hot_deals,
            ROUND(AVG(ar.compliance_score), 1) AS avg_compliance
        FROM analysis_results ar
        WHERE $where_clause
        GROUP BY call_day
        ORDER BY call_day
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Формируем данные для графика
    $trends = [];
    foreach ($results as $row) {
        $total = (int)$row['total_calls'];
        $successful = (int)$row['successful_calls'];
        $deals_count = (int)$row['deals'];
        $hot = (int)$row['hot_deals'];

        $trends[] = [
            'date' => $row['call_day'],
            'total_calls' => $total,
            'successful_calls' => $successful,
            'deals' => $deals_count,
            'hot_deals' => $hot,
            'avg_compliance' => (float)$row['avg_compliance'],
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'deal_rate' => $total > 0 ? round(($deals_count / $total) * 100, 2) : 0,
            'hot_deal_rate' => $deals_count > 0 ? round(($hot / $deals_count) * 100, 2) : 0
        ];
    }

    // Вычисляем общие тренды
    $total_overall = array_sum(array_column($trends, 'total_calls'));
    $success_overall = array_sum(array_column($trends, 'successful_calls'));
    $deals_overall = array_sum(array_column($trends, 'deals'));
    $hot_overall = array_sum(array_column($trends, 'hot_deals'));

    $summary = [
        'total_calls' => $total_overall,
        'success_rate' => $total_overall > 0 ? round(($success_overall / $total_overall) * 100, 2) : 0,
        'deal_rate' => $total_overall > 0 ? round(($deals_overall / $total_overall) * 100, 2) : 0,
        'hot_deal_rate' => $deals_overall > 0 ? round(($hot_overall / $deals_overall) * 100, 2) : 0
    ];

    echo json_encode([
        'success' => true,
        'data' => $trends,
        'summary' => $summary,
        'meta' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'total_days' => count($trends)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
