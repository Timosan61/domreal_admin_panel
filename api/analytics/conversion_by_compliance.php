<?php
/**
 * API: Влияние Compliance на конверсию
 *
 * Группирует звонки по уровню compliance и показывает конверсию для каждой группы
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
        "ar.compliance_score IS NOT NULL",
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

    // Запрос для влияния compliance на конверсию
    $query = "
        SELECT
            CASE
                WHEN ar.compliance_score < 30 THEN '< 30%'
                WHEN ar.compliance_score < 50 THEN '30-50%'
                WHEN ar.compliance_score < 70 THEN '50-70%'
                WHEN ar.compliance_score < 90 THEN '70-90%'
                ELSE '≥ 90%'
            END AS compliance_group,
            MIN(ar.compliance_score) AS min_compliance,
            MAX(ar.compliance_score) AS max_compliance,
            COUNT(*) AS total_calls,
            SUM(CASE WHEN ar.is_successful = 1 THEN 1 ELSE 0 END) AS successful_calls,
            SUM(CASE WHEN ar.deal_status IS NOT NULL THEN 1 ELSE 0 END) AS deals,
            SUM(CASE WHEN ar.deal_status = 'Горячий' THEN 1 ELSE 0 END) AS hot_deals,
            ROUND(AVG(ar.compliance_score), 1) AS avg_compliance
        FROM analysis_results ar
        WHERE $where_clause
        GROUP BY compliance_group
        ORDER BY min_compliance
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Вычисляем проценты для каждой группы
    $compliance_data = [];
    foreach ($results as $row) {
        $total = (int)$row['total_calls'];
        $successful = (int)$row['successful_calls'];
        $deals_count = (int)$row['deals'];
        $hot = (int)$row['hot_deals'];

        $compliance_data[] = [
            'compliance_group' => $row['compliance_group'],
            'min_compliance' => (int)$row['min_compliance'],
            'max_compliance' => (int)$row['max_compliance'],
            'avg_compliance' => (float)$row['avg_compliance'],
            'total_calls' => $total,
            'successful_calls' => $successful,
            'deals' => $deals_count,
            'hot_deals' => $hot,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'deal_rate' => $total > 0 ? round(($deals_count / $total) * 100, 2) : 0,
            'hot_deal_rate' => $deals_count > 0 ? round(($hot / $deals_count) * 100, 2) : 0
        ];
    }

    // Вычисляем общую корреляцию
    $correlation = null;
    if (count($compliance_data) >= 2) {
        // Простая корреляция: разница между высоким и низким compliance
        $high_compliance = end($compliance_data);
        $low_compliance = reset($compliance_data);
        $correlation = [
            'high_compliance_success_rate' => $high_compliance['success_rate'],
            'low_compliance_success_rate' => $low_compliance['success_rate'],
            'difference' => round($high_compliance['success_rate'] - $low_compliance['success_rate'], 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $compliance_data,
        'correlation' => $correlation,
        'meta' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'total_groups' => count($compliance_data)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
