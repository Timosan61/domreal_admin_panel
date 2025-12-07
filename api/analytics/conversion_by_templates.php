<?php
/**
 * API: Конверсия по шаблонам
 *
 * Показывает эффективность разных шаблонов анализа
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
        "ar.template_id IS NOT NULL",
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

    // Запрос для конверсии по шаблонам
    $query = "
        SELECT
            ar.template_id,
            t.name AS template_name,
            t.description AS template_description,
            COUNT(*) AS total_calls,
            SUM(CASE WHEN ar.is_successful = 1 THEN 1 ELSE 0 END) AS successful_calls,
            SUM(CASE WHEN ar.deal_status IS NOT NULL THEN 1 ELSE 0 END) AS deals,
            SUM(CASE WHEN ar.deal_status = 'Горячий' THEN 1 ELSE 0 END) AS hot_deals,
            ROUND(AVG(ar.compliance_score), 1) AS avg_compliance,
            ROUND(AVG(ar.call_duration_sec / 60.0), 1) AS avg_duration_min
        FROM analysis_results ar
        LEFT JOIN analysis_templates t ON ar.template_id = t.template_id
        WHERE $where_clause
        GROUP BY ar.template_id, t.name, t.description
        HAVING COUNT(*) > 0
        ORDER BY successful_calls DESC, template_name
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Вычисляем проценты для каждого шаблона
    $templates_data = [];
    foreach ($results as $row) {
        $total = (int)$row['total_calls'];
        $successful = (int)$row['successful_calls'];
        $deals_count = (int)$row['deals'];
        $hot = (int)$row['hot_deals'];

        $templates_data[] = [
            'template_id' => $row['template_id'],
            'template_name' => $row['template_name'] ?? 'Неизвестный шаблон',
            'template_description' => $row['template_description'],
            'total_calls' => $total,
            'successful_calls' => $successful,
            'deals' => $deals_count,
            'hot_deals' => $hot,
            'avg_compliance' => (float)$row['avg_compliance'],
            'avg_duration_min' => (float)$row['avg_duration_min'],
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'deal_rate' => $total > 0 ? round(($deals_count / $total) * 100, 2) : 0,
            'hot_deal_rate' => $deals_count > 0 ? round(($hot / $deals_count) * 100, 2) : 0
        ];
    }

    // Находим лучший и худший шаблоны
    $best_template = null;
    $worst_template = null;

    if (count($templates_data) > 0) {
        usort($templates_data, fn($a, $b) => $b['success_rate'] <=> $a['success_rate']);
        $best_template = reset($templates_data);
        $worst_template = end($templates_data);
    }

    echo json_encode([
        'success' => true,
        'data' => $templates_data,
        'insights' => [
            'best_template' => $best_template,
            'worst_template' => $worst_template
        ],
        'meta' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'total_templates' => count($templates_data)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
