<?php
/**
 * API endpoint для управления маппингом навыков на контент Moodle
 *
 * Endpoints:
 *   GET    ?action=list                    - Список всех навыков (из analysis_questions)
 *   GET    ?action=mappings                - Список маппингов навык → контент
 *   GET    ?action=get&id={mapping_id}     - Получить маппинг
 *   POST   ?action=create                  - Создать маппинг
 *   PATCH  ?action=update&id={mapping_id}  - Обновить маппинг
 *   DELETE ?action=delete&id={mapping_id}  - Удалить маппинг
 *   GET    ?action=categories              - Список категорий навыков
 *   GET    ?action=moodle_blocks           - Список контент-блоков из Moodle
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
$mappingId = $_GET['id'] ?? null;

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
            handleListSkills($pdo, $orgId);
            break;

        case 'mappings':
            handleListMappings($pdo, $orgId);
            break;

        case 'get':
            if (!$mappingId) {
                throw new Exception('Mapping ID required');
            }
            handleGetMapping($pdo, $mappingId, $orgId);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            handleCreateMapping($pdo, $orgId);
            break;

        case 'update':
            if (!in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'POST'])) {
                throw new Exception('PATCH or POST method required');
            }
            if (!$mappingId) {
                throw new Exception('Mapping ID required');
            }
            handleUpdateMapping($pdo, $mappingId, $orgId);
            break;

        case 'delete':
            if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
                throw new Exception('DELETE or POST method required');
            }
            if (!$mappingId) {
                throw new Exception('Mapping ID required');
            }
            handleDeleteMapping($pdo, $mappingId, $orgId);
            break;

        case 'categories':
            handleListCategories($pdo, $orgId);
            break;

        case 'moodle_blocks':
            handleListMoodleBlocks($orgId);
            break;

        case 'manager_profiles':
            handleManagerProfiles($pdo, $orgId);
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
 * Список всех навыков (вопросов из шаблонов) с их маппингами
 */
function handleListSkills($pdo, $orgId) {
    $stmt = $pdo->prepare("
        SELECT
            q.question_id,
            q.template_id,
            q.question_code,
            q.question_text,
            q.hint_text,
            q.answer_type,
            t.name as template_name,
            t.template_type,
            t.is_active as template_active,
            -- Маппинг на контент (если есть)
            scm.mapping_id,
            scm.skill_name,
            scm.skill_category,
            scm.moodle_anchor_id,
            scm.threshold_warning,
            scm.threshold_critical,
            scm.is_active as mapping_active
        FROM analysis_questions q
        INNER JOIN analysis_templates t ON q.template_id = t.template_id
        LEFT JOIN skill_content_mapping scm ON q.question_id = scm.question_id
            AND scm.org_id = :org_id
        WHERE t.org_id = :org_id2
          AND (t.is_system IS NULL OR t.is_system = 0)
        ORDER BY t.name, q.question_order
    ");

    $stmt->execute([
        'org_id' => $orgId,
        'org_id2' => $orgId
    ]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группируем по шаблонам
    $grouped = [];
    foreach ($skills as $skill) {
        $templateId = $skill['template_id'];
        if (!isset($grouped[$templateId])) {
            $grouped[$templateId] = [
                'template_id' => $templateId,
                'template_name' => $skill['template_name'],
                'template_type' => $skill['template_type'],
                'template_active' => (bool)$skill['template_active'],
                'questions' => []
            ];
        }

        $grouped[$templateId]['questions'][] = [
            'question_id' => $skill['question_id'],
            'question_code' => $skill['question_code'],
            'question_text' => $skill['question_text'],
            'hint_text' => $skill['hint_text'],
            'answer_type' => $skill['answer_type'],
            'has_mapping' => !empty($skill['mapping_id']),
            'mapping' => $skill['mapping_id'] ? [
                'mapping_id' => $skill['mapping_id'],
                'skill_name' => $skill['skill_name'],
                'skill_category' => $skill['skill_category'],
                'moodle_anchor_id' => $skill['moodle_anchor_id'],
                'threshold_warning' => (float)$skill['threshold_warning'],
                'threshold_critical' => (float)$skill['threshold_critical'],
                'is_active' => (bool)$skill['mapping_active']
            ] : null
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($grouped)
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Список всех маппингов навык → контент
 */
function handleListMappings($pdo, $orgId) {
    $stmt = $pdo->prepare("
        SELECT
            scm.*,
            q.question_text,
            t.name as template_name,
            sc.category_name
        FROM skill_content_mapping scm
        INNER JOIN analysis_questions q ON scm.question_id = q.question_id
        INNER JOIN analysis_templates t ON q.template_id = t.template_id
        LEFT JOIN skill_categories sc ON scm.skill_category = sc.category_code
            AND sc.org_id = scm.org_id
        WHERE scm.org_id = :org_id
          AND (t.is_system IS NULL OR t.is_system = 0)
        ORDER BY scm.priority DESC, scm.skill_name
    ");

    $stmt->execute(['org_id' => $orgId]);
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert types
    foreach ($mappings as &$m) {
        $m['threshold_warning'] = (float)$m['threshold_warning'];
        $m['threshold_critical'] = (float)$m['threshold_critical'];
        $m['consecutive_fails_trigger'] = (int)$m['consecutive_fails_trigger'];
        $m['training_duration_minutes'] = (int)$m['training_duration_minutes'];
        $m['priority'] = (int)$m['priority'];
        $m['is_active'] = (bool)$m['is_active'];
    }

    echo json_encode([
        'success' => true,
        'data' => $mappings
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Получить конкретный маппинг
 */
function handleGetMapping($pdo, $mappingId, $orgId) {
    $stmt = $pdo->prepare("
        SELECT
            scm.*,
            q.question_text,
            q.question_code,
            t.name as template_name,
            t.template_id
        FROM skill_content_mapping scm
        INNER JOIN analysis_questions q ON scm.question_id = q.question_id
        INNER JOIN analysis_templates t ON q.template_id = t.template_id
        WHERE scm.mapping_id = :mapping_id AND scm.org_id = :org_id
    ");

    $stmt->execute([
        'mapping_id' => $mappingId,
        'org_id' => $orgId
    ]);

    $mapping = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mapping) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Mapping not found'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Convert types
    $mapping['threshold_warning'] = (float)$mapping['threshold_warning'];
    $mapping['threshold_critical'] = (float)$mapping['threshold_critical'];
    $mapping['consecutive_fails_trigger'] = (int)$mapping['consecutive_fails_trigger'];
    $mapping['training_duration_minutes'] = (int)$mapping['training_duration_minutes'];
    $mapping['priority'] = (int)$mapping['priority'];
    $mapping['is_active'] = (bool)$mapping['is_active'];

    echo json_encode([
        'success' => true,
        'data' => $mapping
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Создать маппинг навыка на контент
 */
function handleCreateMapping($pdo, $orgId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['question_id'])) {
        throw new Exception('question_id is required');
    }

    // Проверяем что вопрос существует и не принадлежит системному шаблону
    $stmt = $pdo->prepare("
        SELECT q.question_id, q.question_code, q.question_text
        FROM analysis_questions q
        INNER JOIN analysis_templates t ON q.template_id = t.template_id
        WHERE q.question_id = :question_id
          AND t.org_id = :org_id
          AND (t.is_system IS NULL OR t.is_system = 0)
    ");
    $stmt->execute([
        'question_id' => $input['question_id'],
        'org_id' => $orgId
    ]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        throw new Exception('Question not found');
    }

    // Проверяем что маппинг для этого вопроса еще не существует
    $stmt = $pdo->prepare("
        SELECT mapping_id FROM skill_content_mapping
        WHERE question_id = :question_id AND org_id = :org_id
    ");
    $stmt->execute([
        'question_id' => $input['question_id'],
        'org_id' => $orgId
    ]);
    if ($stmt->fetch()) {
        throw new Exception('Mapping for this question already exists');
    }

    // Генерируем mapping_id
    $mappingId = 'map-' . substr(md5(uniqid(rand(), true)), 0, 12);

    $stmt = $pdo->prepare("
        INSERT INTO skill_content_mapping
        (mapping_id, org_id, question_id, question_code, skill_name, skill_description,
         skill_category, threshold_warning, threshold_critical, consecutive_fails_trigger,
         moodle_anchor_id, moodle_page_id, moodle_course_id, training_duration_minutes,
         priority, is_active, created_at, updated_at)
        VALUES
        (:mapping_id, :org_id, :question_id, :question_code, :skill_name, :skill_description,
         :skill_category, :threshold_warning, :threshold_critical, :consecutive_fails_trigger,
         :moodle_anchor_id, :moodle_page_id, :moodle_course_id, :training_duration_minutes,
         :priority, 1, NOW(), NOW())
    ");

    $stmt->execute([
        'mapping_id' => $mappingId,
        'org_id' => $orgId,
        'question_id' => $input['question_id'],
        'question_code' => $question['question_code'],
        'skill_name' => $input['skill_name'] ?? $question['question_text'],
        'skill_description' => $input['skill_description'] ?? null,
        'skill_category' => $input['skill_category'] ?? null,
        'threshold_warning' => $input['threshold_warning'] ?? 70.0,
        'threshold_critical' => $input['threshold_critical'] ?? 50.0,
        'consecutive_fails_trigger' => $input['consecutive_fails_trigger'] ?? 3,
        'moodle_anchor_id' => $input['moodle_anchor_id'] ?? null,
        'moodle_page_id' => $input['moodle_page_id'] ?? null,
        'moodle_course_id' => $input['moodle_course_id'] ?? null,
        'training_duration_minutes' => $input['training_duration_minutes'] ?? 15,
        'priority' => $input['priority'] ?? 100
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'mapping_id' => $mappingId,
            'message' => 'Skill mapping created successfully'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Обновить маппинг
 */
function handleUpdateMapping($pdo, $mappingId, $orgId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('No data provided');
    }

    // Проверяем существование
    $stmt = $pdo->prepare("
        SELECT mapping_id FROM skill_content_mapping
        WHERE mapping_id = :mapping_id AND org_id = :org_id
    ");
    $stmt->execute(['mapping_id' => $mappingId, 'org_id' => $orgId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Mapping not found'], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Формируем UPDATE запрос
    $updates = [];
    $params = ['mapping_id' => $mappingId];

    $allowedFields = [
        'skill_name', 'skill_description', 'skill_category',
        'threshold_warning', 'threshold_critical', 'consecutive_fails_trigger',
        'moodle_anchor_id', 'moodle_page_id', 'moodle_course_id',
        'training_duration_minutes', 'priority', 'is_active'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = :$field";
            $params[$field] = $input[$field];
        }
    }

    if (empty($updates)) {
        throw new Exception('No valid fields to update');
    }

    $updates[] = 'updated_at = NOW()';
    $sql = "UPDATE skill_content_mapping SET " . implode(', ', $updates) . " WHERE mapping_id = :mapping_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'data' => [
            'mapping_id' => $mappingId,
            'message' => 'Skill mapping updated successfully'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Удалить маппинг
 */
function handleDeleteMapping($pdo, $mappingId, $orgId) {
    // Проверяем существование
    $stmt = $pdo->prepare("
        SELECT mapping_id FROM skill_content_mapping
        WHERE mapping_id = :mapping_id AND org_id = :org_id
    ");
    $stmt->execute(['mapping_id' => $mappingId, 'org_id' => $orgId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Mapping not found'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM skill_content_mapping WHERE mapping_id = :mapping_id");
    $stmt->execute(['mapping_id' => $mappingId]);

    echo json_encode([
        'success' => true,
        'data' => [
            'mapping_id' => $mappingId,
            'message' => 'Skill mapping deleted successfully'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Список категорий навыков
 */
function handleListCategories($pdo, $orgId) {
    $stmt = $pdo->prepare("
        SELECT
            category_id,
            category_code,
            category_name,
            category_description,
            category_icon,
            display_order
        FROM skill_categories
        WHERE org_id = :org_id AND is_active = 1
        ORDER BY display_order ASC
    ");

    $stmt->execute(['org_id' => $orgId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $categories
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Список контент-блоков из Moodle
 */
function handleListMoodleBlocks($orgId) {
    // Подключаемся к Moodle БД
    require_once __DIR__ . '/../config/moodle_database.php';

    try {
        $moodleDb = new MoodleDatabase();
        $conn = $moodleDb->getConnection();

        if (!$conn) {
            throw new Exception('Moodle database connection failed');
        }

        // Получаем контент-блоки из Moodle
        // Структура зависит от конкретной настройки Moodle
        $stmt = $conn->prepare("
            SELECT
                cb.id as block_id,
                cb.anchor_id,
                cb.title as block_title,
                p.id as page_id,
                p.name as page_name,
                c.id as course_id,
                c.fullname as course_name
            FROM mdl_content_blocks cb
            INNER JOIN mdl_book_chapters p ON cb.chapter_id = p.id
            INNER JOIN mdl_book b ON p.bookid = b.id
            INNER JOIN mdl_course c ON b.course = c.id
            WHERE c.visible = 1
            ORDER BY c.fullname, p.pagenum, cb.position
        ");

        $stmt->execute();
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Группируем по курсам и страницам
        $grouped = [];
        foreach ($blocks as $block) {
            $courseId = $block['course_id'];
            $pageId = $block['page_id'];

            if (!isset($grouped[$courseId])) {
                $grouped[$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $block['course_name'],
                    'pages' => []
                ];
            }

            if (!isset($grouped[$courseId]['pages'][$pageId])) {
                $grouped[$courseId]['pages'][$pageId] = [
                    'page_id' => $pageId,
                    'page_name' => $block['page_name'],
                    'blocks' => []
                ];
            }

            $grouped[$courseId]['pages'][$pageId]['blocks'][] = [
                'block_id' => $block['block_id'],
                'anchor_id' => $block['anchor_id'],
                'block_title' => $block['block_title']
            ];
        }

        // Преобразуем в массивы
        foreach ($grouped as &$course) {
            $course['pages'] = array_values($course['pages']);
        }

        echo json_encode([
            'success' => true,
            'data' => array_values($grouped)
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        // Если не удалось подключиться к Moodle, возвращаем пустой список
        echo json_encode([
            'success' => true,
            'data' => [],
            'warning' => 'Could not connect to Moodle database: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Профили навыков менеджеров
 */
function handleManagerProfiles($pdo, $orgId) {
    $managerId = $_GET['manager'] ?? null;

    $sql = "
        SELECT
            msp.*,
            scm.skill_name,
            sc.category_name
        FROM manager_skill_profiles msp
        LEFT JOIN skill_content_mapping scm ON msp.skill_code = scm.question_code
            AND msp.org_id = scm.org_id
        LEFT JOIN skill_categories sc ON scm.skill_category = sc.category_code
            AND sc.org_id = msp.org_id
        WHERE msp.org_id = :org_id
    ";

    $params = ['org_id' => $orgId];

    if ($managerId) {
        $sql .= " AND msp.employee_full_name = :manager";
        $params['manager'] = $managerId;
    }

    $sql .= " ORDER BY msp.employee_full_name, msp.skill_code";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группируем по менеджерам
    $grouped = [];
    foreach ($profiles as $p) {
        $manager = $p['employee_full_name'];
        if (!isset($grouped[$manager])) {
            $grouped[$manager] = [
                'employee_full_name' => $manager,
                'skills' => []
            ];
        }

        $grouped[$manager]['skills'][] = [
            'skill_code' => $p['skill_code'],
            'skill_name' => $p['skill_name'] ?? $p['skill_code'],
            'category_name' => $p['category_name'],
            'success_rate' => (float)$p['success_rate'],
            'total_assessments' => (int)$p['total_assessments'],
            'consecutive_fails' => (int)$p['consecutive_fails'],
            'trend' => $p['trend'],
            'last_5_results' => $p['last_5_results'],
            'trainings_completed' => (int)$p['trainings_completed'],
            'last_training_date' => $p['last_training_date'],
            'last_assessment_at' => $p['last_assessment_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($grouped)
    ], JSON_UNESCAPED_UNICODE);
}
