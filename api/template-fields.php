<?php
/**
 * API для управления полями шаблонов (template_field_settings)
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

// Parse path: /api/template-fields/{templateId} or /api/template-fields/{templateId}/{fieldCode}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$templateId = $_GET['template_id'] ?? ($pathParts[2] ?? null);
$fieldCode = $_GET['field_code'] ?? ($pathParts[3] ?? null);
$org_id = $_GET['org_id'] ?? 'org-legacy';

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($templateId && $fieldCode) {
                getField($db, $templateId, $fieldCode, $org_id);
            } elseif ($templateId) {
                listFields($db, $templateId, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID required']);
            }
            break;
        case 'POST':
            if ($templateId) {
                createField($db, $templateId, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID required']);
            }
            break;
        case 'PUT':
        case 'PATCH':
            if ($templateId && $fieldCode) {
                updateField($db, $templateId, $fieldCode, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID and Field Code required']);
            }
            break;
        case 'DELETE':
            if ($templateId && $fieldCode) {
                deleteField($db, $templateId, $fieldCode, $org_id);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID and Field Code required']);
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

function listFields($db, $templateId, $org_id) {
    $stmt = $db->prepare("
        SELECT *
        FROM template_field_settings
        WHERE template_id = :template_id AND org_id = :org_id
        ORDER BY display_order, id
    ");
    $stmt->execute([':template_id' => $templateId, ':org_id' => $org_id]);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fields as &$field) {
        $field['include_in_context'] = (bool)$field['include_in_context'];
        $field['validate_correctness'] = (bool)$field['validate_correctness'];
        $field['auto_fill_if_empty'] = (bool)$field['auto_fill_if_empty'];
        $field['is_active'] = (bool)$field['is_active'];
    }

    echo json_encode(['success' => true, 'fields' => $fields]);
}

function getField($db, $templateId, $fieldCode, $org_id) {
    $stmt = $db->prepare("
        SELECT *
        FROM template_field_settings
        WHERE template_id = :template_id AND field_code = :field_code AND org_id = :org_id
    ");
    $stmt->execute([
        ':template_id' => $templateId,
        ':field_code' => $fieldCode,
        ':org_id' => $org_id
    ]);
    $field = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$field) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Field not found']);
        return;
    }

    $field['include_in_context'] = (bool)$field['include_in_context'];
    $field['validate_correctness'] = (bool)$field['validate_correctness'];
    $field['auto_fill_if_empty'] = (bool)$field['auto_fill_if_empty'];
    $field['is_active'] = (bool)$field['is_active'];

    echo json_encode(['success' => true, 'field' => $field]);
}

function createField($db, $templateId, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['field_code']) || empty($input['field_label'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'field_code and field_label required']);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO template_field_settings (
            template_id, org_id, field_code, field_label, field_type, field_category,
            crm_system, include_in_context, validate_correctness, auto_fill_if_empty,
            display_order, emoji, hint, is_active
        ) VALUES (
            :template_id, :org_id, :field_code, :field_label, :field_type, :field_category,
            :crm_system, :include_in_context, :validate_correctness, :auto_fill_if_empty,
            :display_order, :emoji, :hint, :is_active
        )
    ");

    $stmt->execute([
        ':template_id' => $templateId,
        ':org_id' => $org_id,
        ':field_code' => $input['field_code'],
        ':field_label' => $input['field_label'],
        ':field_type' => $input['field_type'] ?? 'string',
        ':field_category' => $input['field_category'] ?? 'standard',
        ':crm_system' => $input['crm_system'] ?? 'none',
        ':include_in_context' => $input['include_in_context'] ?? false,
        ':validate_correctness' => $input['validate_correctness'] ?? false,
        ':auto_fill_if_empty' => $input['auto_fill_if_empty'] ?? false,
        ':display_order' => $input['display_order'] ?? 0,
        ':emoji' => $input['emoji'] ?? null,
        ':hint' => $input['hint'] ?? null,
        ':is_active' => $input['is_active'] ?? true
    ]);

    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function updateField($db, $templateId, $fieldCode, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    // For PATCH requests, only update fields that are provided
    // First, get current field values
    $stmt = $db->prepare("
        SELECT * FROM template_field_settings
        WHERE template_id = :template_id AND field_code = :field_code AND org_id = :org_id
    ");
    $stmt->execute([':template_id' => $templateId, ':field_code' => $fieldCode, ':org_id' => $org_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Field not found']);
        return;
    }

    // Merge input with current values (input takes precedence)
    $merged = [
        'field_label' => $input['field_label'] ?? $current['field_label'],
        'field_type' => $input['field_type'] ?? $current['field_type'],
        'field_category' => $input['field_category'] ?? $current['field_category'],
        'crm_system' => $input['crm_system'] ?? $current['crm_system'],
        'include_in_context' => isset($input['include_in_context']) ? $input['include_in_context'] : $current['include_in_context'],
        'validate_correctness' => isset($input['validate_correctness']) ? $input['validate_correctness'] : $current['validate_correctness'],
        'auto_fill_if_empty' => isset($input['auto_fill_if_empty']) ? $input['auto_fill_if_empty'] : $current['auto_fill_if_empty'],
        'display_order' => $input['display_order'] ?? $current['display_order'],
        'emoji' => array_key_exists('emoji', $input) ? $input['emoji'] : $current['emoji'],
        'hint' => array_key_exists('hint', $input) ? $input['hint'] : $current['hint'],
        'is_active' => isset($input['is_active']) ? $input['is_active'] : $current['is_active']
    ];

    $stmt = $db->prepare("
        UPDATE template_field_settings SET
            field_label = :field_label,
            field_type = :field_type,
            field_category = :field_category,
            crm_system = :crm_system,
            include_in_context = :include_in_context,
            validate_correctness = :validate_correctness,
            auto_fill_if_empty = :auto_fill_if_empty,
            display_order = :display_order,
            emoji = :emoji,
            hint = :hint,
            is_active = :is_active
        WHERE template_id = :template_id AND field_code = :field_code AND org_id = :org_id
    ");

    $stmt->execute([
        ':template_id' => $templateId,
        ':field_code' => $fieldCode,
        ':org_id' => $org_id,
        ':field_label' => $merged['field_label'],
        ':field_type' => $merged['field_type'],
        ':field_category' => $merged['field_category'],
        ':crm_system' => $merged['crm_system'],
        ':include_in_context' => $merged['include_in_context'],
        ':validate_correctness' => $merged['validate_correctness'],
        ':auto_fill_if_empty' => $merged['auto_fill_if_empty'],
        ':display_order' => $merged['display_order'],
        ':emoji' => $merged['emoji'],
        ':hint' => $merged['hint'],
        ':is_active' => $merged['is_active']
    ]);

    echo json_encode(['success' => true]);
}

function deleteField($db, $templateId, $fieldCode, $org_id) {
    $stmt = $db->prepare("
        DELETE FROM template_field_settings
        WHERE template_id = :template_id AND field_code = :field_code AND org_id = :org_id
    ");
    $stmt->execute([
        ':template_id' => $templateId,
        ':field_code' => $fieldCode,
        ':org_id' => $org_id
    ]);

    echo json_encode(['success' => true]);
}
