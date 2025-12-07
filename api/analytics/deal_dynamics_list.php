<?php
/**
 * API для получения списка сделок с анализом динамики
 * GET /api/analytics/deal_dynamics_list.php
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
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$deal_status = isset($_GET['deal_status']) ? $_GET['deal_status'] : '';
$manager = isset($_GET['manager']) ? $_GET['manager'] : '';

$database = new Database();
$db = $database->getConnection();

try {
    $conditions = [
        "ar.created_at >= :date_from",
        "ar.created_at <= :date_to",
        "ar.deal_status IS NOT NULL"
    ];

    if ($deal_status) {
        $conditions[] = "ar.deal_status = :deal_status";
    }

    if ($manager) {
        $conditions[] = "ar.employee_full_name = :manager";
    }

    $where_clause = implode(' AND ', $conditions);

    $query = "
        SELECT
            ar.callid,
            ar.employee_full_name as employee_name,
            ar.client_phone,
            ar.call_duration_sec,
            ar.call_date,
            ar.deal_status,
            ar.deal_objections,
            ar.deal_next_steps,
            ar.summary_text,
            ar.script_compliance_score_v4 as compliance_score,
            ar.is_successful,
            ar.crm_requisition_id,
            ar.crm_funnel_name,
            ar.crm_step_name
        FROM analysis_results ar
        WHERE $where_clause
        ORDER BY ar.call_date DESC
        LIMIT 50
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':date_from', $date_from . ' 00:00:00', PDO::PARAM_STR);
    $stmt->bindValue(':date_to', $date_to . ' 23:59:59', PDO::PARAM_STR);

    if ($deal_status) {
        $stmt->bindValue(':deal_status', $deal_status, PDO::PARAM_STR);
    }

    if ($manager) {
        $stmt->bindValue(':manager', $manager, PDO::PARAM_STR);
    }

    $stmt->execute();
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Форматируем данные
    foreach ($deals as &$deal) {
        $deal['call_date'] = date('d.m.Y H:i', strtotime($deal['call_date']));
        $deal['compliance_score'] = $deal['compliance_score'] ?: 0;
    }

    // Статистика
    $stats = [
        'total_deals' => count($deals),
        'hot_deals' => count(array_filter($deals, fn($d) => $d['deal_status'] === 'Горячий')),
        'warm_deals' => count(array_filter($deals, fn($d) => $d['deal_status'] === 'Теплый')),
        'cold_deals' => count(array_filter($deals, fn($d) => $d['deal_status'] === 'Холодный')),
        'successful' => count(array_filter($deals, fn($d) => $d['is_successful'] == 1))
    ];

    echo json_encode([
        'success' => true,
        'deals' => $deals,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
