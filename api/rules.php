<?php
/**
 * API для управления правилами шаблонов (template_rules)
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
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';
$org_id = $_GET['org_id'] ?? 'org-legacy';

try {
    switch ($action) {
        case 'list':
            listRules($db, $org_id);
            break;
        case 'get':
            $rule_id = $_GET['id'] ?? '';
            getRule($db, $rule_id);
            break;
        case 'create':
            createRule($db, $org_id);
            break;
        case 'update':
            $rule_id = $_GET['id'] ?? '';
            updateRule($db, $rule_id);
            break;
        case 'toggle':
            $rule_id = $_GET['id'] ?? '';
            toggleRule($db, $rule_id);
            break;
        case 'delete':
            $rule_id = $_GET['id'] ?? '';
            deleteRule($db, $rule_id);
            break;
        case 'crm-fields':
            getCrmFields($db);
            break;
        case 'templates':
            listTemplates($db, $org_id);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Список всех правил
 */
function listRules($db, $org_id) {
    $stmt = $db->prepare("
        SELECT
            tr.rule_id,
            tr.org_id,
            tr.template_id,
            tr.name,
            tr.description,
            tr.priority,
            tr.is_active,
            tr.created_at,
            tr.updated_at,
            at.name as template_name
        FROM template_rules tr
        LEFT JOIN analysis_templates at ON tr.template_id = at.template_id
        WHERE tr.org_id = :org_id
        ORDER BY tr.priority DESC, tr.created_at DESC
    ");
    $stmt->execute([':org_id' => $org_id]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем условия для каждого правила
    foreach ($rules as &$rule) {
        $rule['condition_groups'] = getConditionGroups($db, $rule['rule_id']);
        $rule['is_active'] = (bool)$rule['is_active'];
    }

    echo json_encode($rules);
}

/**
 * Получить одно правило
 */
function getRule($db, $rule_id) {
    if (empty($rule_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rule ID required']);
        return;
    }

    $stmt = $db->prepare("
        SELECT
            tr.rule_id,
            tr.org_id,
            tr.template_id,
            tr.name,
            tr.description,
            tr.priority,
            tr.is_active,
            tr.created_at,
            tr.updated_at,
            at.name as template_name
        FROM template_rules tr
        LEFT JOIN analysis_templates at ON tr.template_id = at.template_id
        WHERE tr.rule_id = :rule_id
    ");
    $stmt->execute([':rule_id' => $rule_id]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        http_response_code(404);
        echo json_encode(['error' => 'Rule not found']);
        return;
    }

    $rule['condition_groups'] = getConditionGroups($db, $rule_id);
    $rule['is_active'] = (bool)$rule['is_active'];

    echo json_encode($rule);
}

/**
 * Получить группы условий для правила
 */
function getConditionGroups($db, $rule_id) {
    $stmt = $db->prepare("
        SELECT group_id, group_operator, group_order
        FROM rule_condition_groups
        WHERE rule_id = :rule_id
        ORDER BY group_order
    ");
    $stmt->execute([':rule_id' => $rule_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($groups as &$group) {
        $stmt = $db->prepare("
            SELECT condition_id, crm_field, operator, value, condition_order
            FROM rule_conditions
            WHERE group_id = :group_id
            ORDER BY condition_order
        ");
        $stmt->execute([':group_id' => $group['group_id']]);
        $group['conditions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $groups;
}

/**
 * Создать правило
 */
function createRule($db, $org_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name']) || empty($input['template_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and template_id required']);
        return;
    }

    $rule_id = 'rule-' . uniqid();

    $db->beginTransaction();

    try {
        // Создаем правило
        $stmt = $db->prepare("
            INSERT INTO template_rules (rule_id, org_id, template_id, name, description, priority, is_active)
            VALUES (:rule_id, :org_id, :template_id, :name, :description, :priority, :is_active)
        ");
        $stmt->execute([
            ':rule_id' => $rule_id,
            ':org_id' => $org_id,
            ':template_id' => $input['template_id'],
            ':name' => $input['name'],
            ':description' => $input['description'] ?? '',
            ':priority' => $input['priority'] ?? 0,
            ':is_active' => $input['is_active'] ?? true
        ]);

        // Создаем группы условий
        if (!empty($input['condition_groups'])) {
            saveConditionGroups($db, $rule_id, $input['condition_groups']);
        }

        $db->commit();

        echo json_encode(['success' => true, 'rule_id' => $rule_id]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Обновить правило
 */
function updateRule($db, $rule_id) {
    if (empty($rule_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rule ID required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $db->beginTransaction();

    try {
        // Обновляем правило
        $stmt = $db->prepare("
            UPDATE template_rules
            SET name = :name,
                description = :description,
                template_id = :template_id,
                priority = :priority,
                is_active = :is_active
            WHERE rule_id = :rule_id
        ");
        $stmt->execute([
            ':rule_id' => $rule_id,
            ':name' => $input['name'],
            ':description' => $input['description'] ?? '',
            ':template_id' => $input['template_id'],
            ':priority' => $input['priority'] ?? 0,
            ':is_active' => $input['is_active'] ?? true
        ]);

        // Удаляем старые условия
        deleteConditionGroups($db, $rule_id);

        // Создаем новые группы условий
        if (!empty($input['condition_groups'])) {
            saveConditionGroups($db, $rule_id, $input['condition_groups']);
        }

        $db->commit();

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Сохранить группы условий
 */
function saveConditionGroups($db, $rule_id, $groups) {
    foreach ($groups as $groupIndex => $group) {
        $group_id = 'grp-' . uniqid();

        $stmt = $db->prepare("
            INSERT INTO rule_condition_groups (group_id, rule_id, group_operator, group_order)
            VALUES (:group_id, :rule_id, :group_operator, :group_order)
        ");
        $stmt->execute([
            ':group_id' => $group_id,
            ':rule_id' => $rule_id,
            ':group_operator' => $group['group_operator'] ?? 'AND',
            ':group_order' => $groupIndex
        ]);

        // Создаем условия в группе
        if (!empty($group['conditions'])) {
            foreach ($group['conditions'] as $condIndex => $condition) {
                $condition_id = 'cond-' . uniqid();

                $stmt = $db->prepare("
                    INSERT INTO rule_conditions (condition_id, group_id, crm_field, operator, value, condition_order)
                    VALUES (:condition_id, :group_id, :crm_field, :operator, :value, :condition_order)
                ");
                $stmt->execute([
                    ':condition_id' => $condition_id,
                    ':group_id' => $group_id,
                    ':crm_field' => $condition['crm_field'],
                    ':operator' => $condition['operator'],
                    ':value' => $condition['value'],
                    ':condition_order' => $condIndex
                ]);
            }
        }
    }
}

/**
 * Удалить группы условий правила
 */
function deleteConditionGroups($db, $rule_id) {
    // Сначала удаляем условия
    $stmt = $db->prepare("
        DELETE rc FROM rule_conditions rc
        INNER JOIN rule_condition_groups rcg ON rc.group_id = rcg.group_id
        WHERE rcg.rule_id = :rule_id
    ");
    $stmt->execute([':rule_id' => $rule_id]);

    // Затем удаляем группы
    $stmt = $db->prepare("DELETE FROM rule_condition_groups WHERE rule_id = :rule_id");
    $stmt->execute([':rule_id' => $rule_id]);
}

/**
 * Переключить активность правила
 */
function toggleRule($db, $rule_id) {
    if (empty($rule_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rule ID required']);
        return;
    }

    $stmt = $db->prepare("
        UPDATE template_rules
        SET is_active = NOT is_active
        WHERE rule_id = :rule_id
    ");
    $stmt->execute([':rule_id' => $rule_id]);

    // Получаем новое значение
    $stmt = $db->prepare("SELECT is_active FROM template_rules WHERE rule_id = :rule_id");
    $stmt->execute([':rule_id' => $rule_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'is_active' => (bool)$result['is_active']]);
}

/**
 * Удалить правило
 */
function deleteRule($db, $rule_id) {
    if (empty($rule_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rule ID required']);
        return;
    }

    $db->beginTransaction();

    try {
        // Удаляем условия и группы
        deleteConditionGroups($db, $rule_id);

        // Удаляем правило
        $stmt = $db->prepare("DELETE FROM template_rules WHERE rule_id = :rule_id");
        $stmt->execute([':rule_id' => $rule_id]);

        $db->commit();

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Получить список CRM полей для условий
 */
function getCrmFields($db) {
    // Возвращаем предопределенный список CRM полей
    $fields = [
        ['field' => 'call_duration', 'label' => 'Длительность звонка (сек)', 'type' => 'number'],
        ['field' => 'direction', 'label' => 'Направление звонка', 'type' => 'select', 'options' => ['INBOUND', 'OUTBOUND']],
        ['field' => 'is_first_contact', 'label' => 'Первый контакт', 'type' => 'boolean'],
        ['field' => 'client_status', 'label' => 'Статус клиента', 'type' => 'string'],
        ['field' => 'funnel_name', 'label' => 'Название воронки', 'type' => 'string'],
        ['field' => 'step_name', 'label' => 'Этап воронки', 'type' => 'string'],
        ['field' => 'department', 'label' => 'Отдел', 'type' => 'string'],
        ['field' => 'solvency_level', 'label' => 'Платежеспособность', 'type' => 'select', 'options' => ['high', 'medium', 'low']],
        ['field' => 'has_previous_calls', 'label' => 'Есть предыдущие звонки', 'type' => 'boolean'],
        ['field' => 'previous_calls_count', 'label' => 'Количество предыдущих звонков', 'type' => 'number']
    ];

    echo json_encode($fields);
}

/**
 * Список шаблонов
 */
function listTemplates($db, $org_id) {
    $stmt = $db->prepare("
        SELECT template_id, name, description, is_active
        FROM analysis_templates
        WHERE org_id = :org_id OR org_id = 'org-legacy'
        ORDER BY name
    ");
    $stmt->execute([':org_id' => $org_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($templates as &$template) {
        $template['is_active'] = (bool)$template['is_active'];
    }

    echo json_encode($templates);
}
