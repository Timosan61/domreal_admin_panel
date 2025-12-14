<?php
/**
 * API для управления настройками алертов шаблонов (template_alert_settings)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Parse path: /api/template-alerts/{templateId}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$templateId = $_GET['template_id'] ?? ($pathParts[2] ?? null);
$org_id = $_GET['org_id'] ?? 'org-legacy';

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($templateId) {
                getAlertSettings($db, $templateId, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID required']);
            }
            break;
        case 'POST':
        case 'PUT':
        case 'PATCH':
            if ($templateId) {
                saveAlertSettings($db, $templateId, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID required']);
            }
            break;
        case 'DELETE':
            if ($templateId) {
                deleteAlertSettings($db, $templateId, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID required']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getAlertSettings($db, $templateId, $org_id) {
    $stmt = $db->prepare("
        SELECT *
        FROM template_alert_settings
        WHERE template_id = :template_id AND org_id = :org_id
    ");
    $stmt->execute([':template_id' => $templateId, ':org_id' => $org_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Return default settings if not found
        echo json_encode([
            'success' => true,
            'settings' => [
                'template_id' => $templateId,
                'org_id' => $org_id,
                'send_alerts_to_crm' => false,
                'crm_field_name' => '',
                'low_threshold' => 1,
                'medium_threshold' => 2,
                'high_threshold' => 4,
                'critical_threshold' => 6,
                'auto_notify_on_high' => true,
                'auto_notify_on_critical' => true,
                'auto_block_on_critical' => false,
                'notification_emails' => []
            ]
        ]);
        return;
    }

    // Convert boolean fields
    $settings['send_alerts_to_crm'] = (bool)$settings['send_alerts_to_crm'];
    $settings['auto_notify_on_high'] = (bool)$settings['auto_notify_on_high'];
    $settings['auto_notify_on_critical'] = (bool)$settings['auto_notify_on_critical'];
    $settings['auto_block_on_critical'] = (bool)$settings['auto_block_on_critical'];

    // Parse JSON emails
    $settings['notification_emails'] = $settings['notification_emails']
        ? json_decode($settings['notification_emails'], true)
        : [];

    echo json_encode(['success' => true, 'settings' => $settings]);
}

function saveAlertSettings($db, $templateId, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    // Check if settings exist
    $stmt = $db->prepare("
        SELECT id FROM template_alert_settings
        WHERE template_id = :template_id AND org_id = :org_id
    ");
    $stmt->execute([':template_id' => $templateId, ':org_id' => $org_id]);
    $exists = $stmt->fetch();

    // Prepare notification_emails as JSON
    $notificationEmails = isset($input['notification_emails'])
        ? json_encode($input['notification_emails'])
        : '[]';

    if ($exists) {
        // Update existing
        $stmt = $db->prepare("
            UPDATE template_alert_settings SET
                send_alerts_to_crm = :send_alerts_to_crm,
                crm_field_name = :crm_field_name,
                low_threshold = :low_threshold,
                medium_threshold = :medium_threshold,
                high_threshold = :high_threshold,
                critical_threshold = :critical_threshold,
                auto_notify_on_high = :auto_notify_on_high,
                auto_notify_on_critical = :auto_notify_on_critical,
                auto_block_on_critical = :auto_block_on_critical,
                notification_emails = :notification_emails
            WHERE template_id = :template_id AND org_id = :org_id
        ");
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO template_alert_settings (
                template_id, org_id, send_alerts_to_crm, crm_field_name,
                low_threshold, medium_threshold, high_threshold, critical_threshold,
                auto_notify_on_high, auto_notify_on_critical, auto_block_on_critical,
                notification_emails
            ) VALUES (
                :template_id, :org_id, :send_alerts_to_crm, :crm_field_name,
                :low_threshold, :medium_threshold, :high_threshold, :critical_threshold,
                :auto_notify_on_high, :auto_notify_on_critical, :auto_block_on_critical,
                :notification_emails
            )
        ");
    }

    $stmt->execute([
        ':template_id' => $templateId,
        ':org_id' => $org_id,
        ':send_alerts_to_crm' => $input['send_alerts_to_crm'] ?? false,
        ':crm_field_name' => $input['crm_field_name'] ?? null,
        ':low_threshold' => $input['low_threshold'] ?? 1,
        ':medium_threshold' => $input['medium_threshold'] ?? 2,
        ':high_threshold' => $input['high_threshold'] ?? 4,
        ':critical_threshold' => $input['critical_threshold'] ?? 6,
        ':auto_notify_on_high' => $input['auto_notify_on_high'] ?? true,
        ':auto_notify_on_critical' => $input['auto_notify_on_critical'] ?? true,
        ':auto_block_on_critical' => $input['auto_block_on_critical'] ?? false,
        ':notification_emails' => $notificationEmails
    ]);

    echo json_encode(['success' => true]);
}

function deleteAlertSettings($db, $templateId, $org_id) {
    $stmt = $db->prepare("
        DELETE FROM template_alert_settings
        WHERE template_id = :template_id AND org_id = :org_id
    ");
    $stmt->execute([
        ':template_id' => $templateId,
        ':org_id' => $org_id
    ]);

    echo json_encode(['success' => true]);
}
