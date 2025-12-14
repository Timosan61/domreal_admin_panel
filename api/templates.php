<?php
/**
 * API endpoint для управления шаблонами анализа
 *
 * Endpoints:
 *   GET    ?action=list           - Список шаблонов
 *   GET    ?action=get&id={id}    - Получить шаблон с вопросами
 *   POST   ?action=create         - Создать шаблон
 *   PATCH  ?action=update&id={id} - Обновить шаблон
 *   PATCH  ?action=toggle&id={id} - Переключить is_active
 *   DELETE ?action=delete&id={id} - Удалить шаблон
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Получаем action из query string
$action = $_GET['action'] ?? 'list';
$templateId = $_GET['id'] ?? null;

// Организация (временно хардкод)
$orgId = 'org-legacy';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    switch ($action) {
        case 'list':
            handleList($pdo, $orgId);
            break;

        case 'get':
            if (!$templateId) {
                throw new Exception('Template ID required');
            }
            handleGet($pdo, $templateId, $orgId);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            handleCreate($pdo, $orgId);
            break;

        case 'update':
            if (!in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'POST'])) {
                throw new Exception('PATCH or POST method required');
            }
            if (!$templateId) {
                throw new Exception('Template ID required');
            }
            handleUpdate($pdo, $templateId, $orgId);
            break;

        case 'toggle':
            if (!in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'POST'])) {
                throw new Exception('PATCH or POST method required');
            }
            if (!$templateId) {
                throw new Exception('Template ID required');
            }
            handleToggle($pdo, $templateId, $orgId);
            break;

        case 'delete':
            if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
                throw new Exception('DELETE or POST method required');
            }
            if (!$templateId) {
                throw new Exception('Template ID required');
            }
            handleDelete($pdo, $templateId, $orgId);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Список всех шаблонов
 */
function handleList($pdo, $orgId) {
    $stmt = $pdo->prepare("
        SELECT
            t.template_id,
            t.name,
            t.description,
            t.template_type,
            t.is_active,
            t.is_default,
            t.is_system,
            t.created_at,
            t.updated_at,
            COUNT(q.question_id) as questions_count
        FROM analysis_templates t
        LEFT JOIN analysis_questions q ON t.template_id = q.template_id
        WHERE t.org_id = :org_id
        GROUP BY t.template_id
        ORDER BY t.is_system DESC, t.created_at DESC
    ");

    $stmt->execute(['org_id' => $orgId]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Преобразуем типы
    foreach ($templates as &$t) {
        $t['is_active'] = (bool)$t['is_active'];
        $t['is_default'] = (bool)$t['is_default'];
        $t['is_system'] = (bool)$t['is_system'];
        $t['questions_count'] = (int)$t['questions_count'];
    }

    echo json_encode([
        'success' => true,
        'data' => $templates
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Получить шаблон с вопросами
 */
function handleGet($pdo, $templateId, $orgId) {
    // Получаем шаблон
    $stmt = $pdo->prepare("
        SELECT
            template_id,
            org_id,
            name,
            description,
            system_prompt,
            template_type,
            is_active,
            is_default,
            is_system,
            created_at,
            updated_at
        FROM analysis_templates
        WHERE template_id = :template_id AND org_id = :org_id
    ");

    $stmt->execute([
        'template_id' => $templateId,
        'org_id' => $orgId
    ]);

    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Template not found'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Получаем вопросы
    $stmt = $pdo->prepare("
        SELECT
            question_id,
            question_order,
            question_text,
            question_code,
            hint_text,
            answer_type,
            scoring_weight,
            is_required
        FROM analysis_questions
        WHERE template_id = :template_id
        ORDER BY question_order ASC
    ");

    $stmt->execute(['template_id' => $templateId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Преобразуем типы
    $template['is_active'] = (bool)$template['is_active'];
    $template['is_default'] = (bool)$template['is_default'];
    $template['is_system'] = (bool)$template['is_system'];

    foreach ($questions as &$q) {
        $q['question_order'] = (int)$q['question_order'];
        $q['scoring_weight'] = (float)$q['scoring_weight'];
        $q['is_required'] = (bool)$q['is_required'];
    }

    $template['questions'] = $questions;

    echo json_encode([
        'success' => true,
        'data' => $template
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Создать шаблон
 */
function handleCreate($pdo, $orgId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['name'])) {
        throw new Exception('Name is required');
    }

    // Генерируем template_id
    $templateId = 'tpl-' . substr(md5(uniqid(rand(), true)), 0, 12);

    $pdo->beginTransaction();

    try {
        // Создаем шаблон
        $stmt = $pdo->prepare("
            INSERT INTO analysis_templates
            (template_id, org_id, name, description, system_prompt, template_type, is_active, is_default, is_system, created_at, updated_at)
            VALUES
            (:template_id, :org_id, :name, :description, :system_prompt, :template_type, 1, :is_default, 0, NOW(), NOW())
        ");

        $stmt->execute([
            'template_id' => $templateId,
            'org_id' => $orgId,
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'system_prompt' => $input['system_prompt'] ?? null,
            'template_type' => $input['template_type'] ?? 'custom',
            'is_default' => isset($input['is_default']) && $input['is_default'] ? 1 : 0
        ]);

        // Создаем вопросы если они есть
        if (!empty($input['questions'])) {
            $stmtQ = $pdo->prepare("
                INSERT INTO analysis_questions
                (question_id, template_id, question_order, question_text, question_code, hint_text, answer_type, scoring_weight, is_required)
                VALUES
                (:question_id, :template_id, :question_order, :question_text, :question_code, :hint_text, :answer_type, :scoring_weight, :is_required)
            ");

            foreach ($input['questions'] as $index => $q) {
                $questionId = 'q-' . substr(md5(uniqid(rand(), true)), 0, 12);

                $stmtQ->execute([
                    'question_id' => $questionId,
                    'template_id' => $templateId,
                    'question_order' => $q['question_order'] ?? ($index + 1),
                    'question_text' => $q['question_text'],
                    'question_code' => $q['question_code'] ?? ('q' . ($index + 1)),
                    'hint_text' => $q['hint_text'] ?? null,
                    'answer_type' => $q['answer_type'] ?? 'yes_no',
                    'scoring_weight' => $q['scoring_weight'] ?? 1.0,
                    'is_required' => isset($q['is_required']) ? ($q['is_required'] ? 1 : 0) : 1
                ]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'data' => [
                'template_id' => $templateId,
                'message' => 'Template created successfully'
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Обновить шаблон
 */
function handleUpdate($pdo, $templateId, $orgId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('No data provided');
    }

    // Проверяем существование и что это не системный шаблон
    $stmt = $pdo->prepare("
        SELECT template_id, is_system FROM analysis_templates
        WHERE template_id = :template_id AND org_id = :org_id
    ");
    $stmt->execute(['template_id' => $templateId, 'org_id' => $orgId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template not found'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $pdo->beginTransaction();

    try {
        // Обновляем шаблон
        $updates = [];
        $params = ['template_id' => $templateId];

        if (isset($input['name'])) {
            $updates[] = 'name = :name';
            $params['name'] = $input['name'];
        }
        if (isset($input['description'])) {
            $updates[] = 'description = :description';
            $params['description'] = $input['description'];
        }
        if (isset($input['system_prompt'])) {
            $updates[] = 'system_prompt = :system_prompt';
            $params['system_prompt'] = $input['system_prompt'];
        }
        if (isset($input['template_type'])) {
            $updates[] = 'template_type = :template_type';
            $params['template_type'] = $input['template_type'];
        }
        if (isset($input['is_default'])) {
            $updates[] = 'is_default = :is_default';
            $params['is_default'] = $input['is_default'] ? 1 : 0;
        }

        if (!empty($updates)) {
            $updates[] = 'updated_at = NOW()';
            $sql = "UPDATE analysis_templates SET " . implode(', ', $updates) . " WHERE template_id = :template_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // Обновляем вопросы если они есть
        if (isset($input['questions'])) {
            // Удаляем старые вопросы
            $stmt = $pdo->prepare("DELETE FROM analysis_questions WHERE template_id = :template_id");
            $stmt->execute(['template_id' => $templateId]);

            // Добавляем новые
            if (!empty($input['questions'])) {
                $stmtQ = $pdo->prepare("
                    INSERT INTO analysis_questions
                    (question_id, template_id, question_order, question_text, question_code, hint_text, answer_type, scoring_weight, is_required)
                    VALUES
                    (:question_id, :template_id, :question_order, :question_text, :question_code, :hint_text, :answer_type, :scoring_weight, :is_required)
                ");

                foreach ($input['questions'] as $index => $q) {
                    $questionId = 'q-' . substr(md5(uniqid(rand(), true)), 0, 12);

                    $stmtQ->execute([
                        'question_id' => $questionId,
                        'template_id' => $templateId,
                        'question_order' => $q['question_order'] ?? ($index + 1),
                        'question_text' => $q['question_text'],
                        'question_code' => $q['question_code'] ?? ('q' . ($index + 1)),
                        'hint_text' => $q['hint_text'] ?? null,
                        'answer_type' => $q['answer_type'] ?? 'yes_no',
                        'scoring_weight' => $q['scoring_weight'] ?? 1.0,
                        'is_required' => isset($q['is_required']) ? ($q['is_required'] ? 1 : 0) : 1
                    ]);
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'data' => [
                'template_id' => $templateId,
                'message' => 'Template updated successfully'
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Переключить активность шаблона
 */
function handleToggle($pdo, $templateId, $orgId) {
    // Получаем текущее состояние
    $stmt = $pdo->prepare("
        SELECT template_id, is_active FROM analysis_templates
        WHERE template_id = :template_id AND org_id = :org_id
    ");
    $stmt->execute(['template_id' => $templateId, 'org_id' => $orgId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template not found'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Переключаем
    $newState = $template['is_active'] ? 0 : 1;

    $stmt = $pdo->prepare("
        UPDATE analysis_templates
        SET is_active = :is_active, updated_at = NOW()
        WHERE template_id = :template_id
    ");
    $stmt->execute(['is_active' => $newState, 'template_id' => $templateId]);

    echo json_encode([
        'success' => true,
        'data' => [
            'template_id' => $templateId,
            'is_active' => (bool)$newState,
            'message' => $newState ? 'Template activated' : 'Template deactivated'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Удалить шаблон
 */
function handleDelete($pdo, $templateId, $orgId) {
    // Проверяем что это не системный шаблон
    $stmt = $pdo->prepare("
        SELECT template_id, is_system FROM analysis_templates
        WHERE template_id = :template_id AND org_id = :org_id
    ");
    $stmt->execute(['template_id' => $templateId, 'org_id' => $orgId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template not found'], JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($template['is_system']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot delete system template'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $pdo->beginTransaction();

    try {
        // Удаляем вопросы
        $stmt = $pdo->prepare("DELETE FROM analysis_questions WHERE template_id = :template_id");
        $stmt->execute(['template_id' => $templateId]);

        // Удаляем шаблон
        $stmt = $pdo->prepare("DELETE FROM analysis_templates WHERE template_id = :template_id");
        $stmt->execute(['template_id' => $templateId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'data' => [
                'template_id' => $templateId,
                'message' => 'Template deleted successfully'
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
