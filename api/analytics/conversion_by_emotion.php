<?php
/**
 * API: Влияние эмоций на конверсию
 *
 * Группирует звонки по тональности (POSITIVE/NEUTRAL/NEGATIVE) и показывает конверсию
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
        "ar.emotion_data IS NOT NULL",
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

    // Запрос для влияния эмоций на конверсию
    $query = "
        SELECT
            JSON_UNQUOTE(JSON_EXTRACT(ar.emotion_data, '$.overall_sentiment')) AS sentiment,
            COUNT(*) AS total_calls,
            SUM(CASE WHEN ar.is_successful = 1 THEN 1 ELSE 0 END) AS successful_calls,
            SUM(CASE WHEN ar.deal_status IS NOT NULL THEN 1 ELSE 0 END) AS deals,
            SUM(CASE WHEN ar.deal_status = 'Горячий' THEN 1 ELSE 0 END) AS hot_deals,
            ROUND(AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(ar.emotion_data, '$.emotional_intensity')) AS DECIMAL(5,2))), 2) AS avg_intensity,
            ROUND(AVG(ar.compliance_score), 1) AS avg_compliance
        FROM analysis_results ar
        WHERE $where_clause
        GROUP BY sentiment
        ORDER BY
            CASE sentiment
                WHEN 'POSITIVE' THEN 1
                WHEN 'NEUTRAL' THEN 2
                WHEN 'NEGATIVE' THEN 3
                ELSE 4
            END
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Вычисляем проценты для каждой эмоции
    $emotion_data = [];
    $sentiment_labels = [
        'POSITIVE' => 'Позитивная',
        'NEUTRAL' => 'Нейтральная',
        'NEGATIVE' => 'Негативная'
    ];

    foreach ($results as $row) {
        $sentiment = $row['sentiment'];
        $total = (int)$row['total_calls'];
        $successful = (int)$row['successful_calls'];
        $deals_count = (int)$row['deals'];
        $hot = (int)$row['hot_deals'];

        $emotion_data[] = [
            'sentiment' => $sentiment,
            'sentiment_label' => $sentiment_labels[$sentiment] ?? $sentiment,
            'total_calls' => $total,
            'successful_calls' => $successful,
            'deals' => $deals_count,
            'hot_deals' => $hot,
            'avg_intensity' => (float)$row['avg_intensity'],
            'avg_compliance' => (float)$row['avg_compliance'],
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'deal_rate' => $total > 0 ? round(($deals_count / $total) * 100, 2) : 0,
            'hot_deal_rate' => $deals_count > 0 ? round(($hot / $deals_count) * 100, 2) : 0
        ];
    }

    // Вычисляем разницу между позитивной и негативной тональностью
    $impact = null;
    $positive = array_values(array_filter($emotion_data, fn($e) => $e['sentiment'] === 'POSITIVE'));
    $negative = array_values(array_filter($emotion_data, fn($e) => $e['sentiment'] === 'NEGATIVE'));

    if (!empty($positive) && !empty($negative)) {
        $impact = [
            'positive_success_rate' => $positive[0]['success_rate'],
            'negative_success_rate' => $negative[0]['success_rate'],
            'difference' => round($positive[0]['success_rate'] - $negative[0]['success_rate'], 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $emotion_data,
        'impact' => $impact,
        'meta' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'total_calls_with_emotion' => array_sum(array_column($emotion_data, 'total_calls'))
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
