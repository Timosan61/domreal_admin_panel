<?php
/**
 * API: Воронка конверсии
 *
 * Возвращает данные для построения воронки:
 * Все звонки → Реальные разговоры → Рабочие звонки → Успешные → Сделки → Горячие сделки
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
    $where_conditions = ["ar.call_date BETWEEN :date_from AND :date_to"];
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

    // Запрос для воронки конверсии
    $query = "
        SELECT
            COUNT(*) AS total_calls,
            SUM(CASE WHEN ar.real_conversation = 1 THEN 1 ELSE 0 END) AS real_conversations,
            SUM(CASE WHEN ar.real_conversation = 1 AND ar.is_work_related = 1 THEN 1 ELSE 0 END) AS work_calls,
            SUM(CASE WHEN ar.real_conversation = 1 AND ar.is_work_related = 1 AND ar.is_successful = 1 THEN 1 ELSE 0 END) AS successful_calls,
            SUM(CASE WHEN ar.real_conversation = 1 AND ar.is_work_related = 1 AND ar.deal_status IS NOT NULL THEN 1 ELSE 0 END) AS deals,
            SUM(CASE WHEN ar.real_conversation = 1 AND ar.is_work_related = 1 AND ar.deal_status = 'Горячий' THEN 1 ELSE 0 END) AS hot_deals
        FROM analysis_results ar
        WHERE $where_clause
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Преобразуем в числа
    $total_calls = (int)$result['total_calls'];
    $real_conversations = (int)$result['real_conversations'];
    $work_calls = (int)$result['work_calls'];
    $successful_calls = (int)$result['successful_calls'];
    $deals = (int)$result['deals'];
    $hot_deals = (int)$result['hot_deals'];

    // Вычисляем проценты
    $funnel = [
        [
            'stage' => 'Все звонки',
            'count' => $total_calls,
            'percentage' => 100,
            'conversion_from_previous' => null
        ],
        [
            'stage' => 'Реальные разговоры',
            'count' => $real_conversations,
            'percentage' => $total_calls > 0 ? round(($real_conversations / $total_calls) * 100, 2) : 0,
            'conversion_from_previous' => $total_calls > 0 ? round(($real_conversations / $total_calls) * 100, 2) : 0
        ],
        [
            'stage' => 'Рабочие звонки',
            'count' => $work_calls,
            'percentage' => $total_calls > 0 ? round(($work_calls / $total_calls) * 100, 2) : 0,
            'conversion_from_previous' => $real_conversations > 0 ? round(($work_calls / $real_conversations) * 100, 2) : 0
        ],
        [
            'stage' => 'Успешные звонки',
            'count' => $successful_calls,
            'percentage' => $total_calls > 0 ? round(($successful_calls / $total_calls) * 100, 2) : 0,
            'conversion_from_previous' => $work_calls > 0 ? round(($successful_calls / $work_calls) * 100, 2) : 0
        ],
        [
            'stage' => 'Сделки',
            'count' => $deals,
            'percentage' => $total_calls > 0 ? round(($deals / $total_calls) * 100, 2) : 0,
            'conversion_from_previous' => $successful_calls > 0 ? round(($deals / $successful_calls) * 100, 2) : 0
        ],
        [
            'stage' => 'Горячие сделки',
            'count' => $hot_deals,
            'percentage' => $total_calls > 0 ? round(($hot_deals / $total_calls) * 100, 2) : 0,
            'conversion_from_previous' => $deals > 0 ? round(($hot_deals / $deals) * 100, 2) : 0
        ]
    ];

    echo json_encode([
        'success' => true,
        'data' => $funnel,
        'meta' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'total_calls' => $total_calls
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
