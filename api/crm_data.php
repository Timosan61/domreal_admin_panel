<?php
/**
 * CRM Data API Endpoint
 * Получение данных JoyWork CRM для звонка
 *
 * GET /api/crm_data.php?callid=123456789
 * GET /api/crm_data.php?phone=+79991234567
 */

header('Content-Type: application/json; charset=utf-8');

// Database connection
require_once __DIR__ . '/../config/database.php';

/**
 * Get CRM data by call ID
 */
function getCrmDataByCallId($pdo, $callid) {
    $stmt = $pdo->prepare("
        SELECT
            crm_funnel_id,
            crm_funnel_name,
            crm_step_id,
            crm_step_name,
            crm_requisition_id,
            crm_last_sync,
            client_phone
        FROM analysis_results
        WHERE callid = :callid
        LIMIT 1
    ");

    $stmt->execute(['callid' => $callid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get CRM data by phone number
 */
function getCrmDataByPhone($pdo, $phone) {
    $stmt = $pdo->prepare("
        SELECT
            crm_funnel_id,
            crm_funnel_name,
            crm_step_id,
            crm_step_name,
            crm_requisition_id,
            crm_last_sync,
            client_phone,
            COUNT(*) as total_calls
        FROM analysis_results
        WHERE client_phone = :phone
        AND crm_funnel_id IS NOT NULL
        GROUP BY
            crm_funnel_id,
            crm_funnel_name,
            crm_step_id,
            crm_step_name,
            crm_requisition_id,
            crm_last_sync,
            client_phone
        ORDER BY crm_last_sync DESC
        LIMIT 1
    ");

    $stmt->execute(['phone' => $phone]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Main execution
 */
try {
    // Get parameters
    $callid = $_GET['callid'] ?? null;
    $phone = $_GET['phone'] ?? null;

    if (!$callid && !$phone) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Required parameter missing: callid or phone'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Get CRM data
    if ($callid) {
        $crmData = getCrmDataByCallId($pdo, $callid);
    } else {
        $crmData = getCrmDataByPhone($pdo, $phone);
    }

    if (!$crmData || !$crmData['crm_funnel_id']) {
        // No CRM data found
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'CRM data not found'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Format response
    $response = [
        'success' => true,
        'data' => [
            'funnel_id' => $crmData['crm_funnel_id'],
            'funnel_name' => $crmData['crm_funnel_name'],
            'step_id' => $crmData['crm_step_id'],
            'step_name' => $crmData['crm_step_name'],
            'requisition_id' => $crmData['crm_requisition_id'],
            'last_sync' => $crmData['crm_last_sync'],
            'phone' => $crmData['client_phone']
        ]
    ];

    if (isset($crmData['total_calls'])) {
        $response['data']['total_calls'] = (int)$crmData['total_calls'];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
