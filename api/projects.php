<?php
/**
 * API для управления проектами
 * GET /api/projects.php - список проектов с pagination
 * GET /api/projects.php?id=X - детали проекта
 * POST /api/projects.php - создание проекта
 * PUT /api/projects.php?id=X - обновление проекта
 * DELETE /api/projects.php?id=X - удаление проекта
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Отключаем кеширование
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit();
}

// Определяем метод запроса
$method = $_SERVER['REQUEST_METHOD'];

// Обработка OPTIONS (CORS preflight)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Получаем параметры
$project_id = isset($_GET['id']) ? intval($_GET['id']) : null;

/**
 * GET - Получение списка проектов или деталей проекта
 */
if ($method === 'GET') {
    // Если указан ID - возвращаем детали проекта
    if ($project_id) {
        getProjectDetails($db, $project_id);
    } else {
        // Возвращаем список проектов с пагинацией
        getProjectsList($db);
    }
}

/**
 * POST - Создание нового проекта
 */
elseif ($method === 'POST') {
    // Проверяем права администратора
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Доступ запрещен. Требуются права администратора."]);
        exit();
    }

    createProject($db);
}

/**
 * PUT - Обновление проекта
 */
elseif ($method === 'PUT') {
    // Проверяем права администратора
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Доступ запрещен. Требуются права администратора."]);
        exit();
    }

    if (!$project_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Project ID is required"]);
        exit();
    }

    updateProject($db, $project_id);
}

/**
 * DELETE - Удаление проекта
 */
elseif ($method === 'DELETE') {
    // Проверяем права администратора
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Доступ запрещен. Требуются права администратора."]);
        exit();
    }

    if (!$project_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Project ID is required"]);
        exit();
    }

    deleteProject($db, $project_id);
}

else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit();
}

/**
 * Получить список проектов с пагинацией
 */
function getProjectsList($db) {
    // Параметры пагинации
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
    $offset = ($page - 1) * $per_page;

    // Фильтры
    $is_active = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Сортировка
    $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
    $sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

    // Базовый запрос с JOIN для получения дополнительной информации
    $query = "SELECT
        p.id,
        p.name,
        p.description,
        p.is_active,
        p.created_at,
        p.updated_at,
        (SELECT COUNT(*) FROM project_integrations WHERE project_id = p.id) as integrations_count,
        (SELECT COUNT(*) FROM project_checklists WHERE project_id = p.id) as checklists_count,
        (SELECT COUNT(*) FROM calls_raw WHERE project_id = p.id) as calls_count,
        pb.stt_balance_seconds,
        pb.ai_balance_seconds,
        pb.stt_total_used_seconds,
        pb.ai_total_used_seconds,
        pas.is_configured as ai_configured,
        pas.llm_provider,
        pas.llm_model
    FROM projects p
    LEFT JOIN project_balances pb ON p.id = pb.project_id
    LEFT JOIN project_ai_settings pas ON p.id = pas.project_id
    WHERE 1=1";

    $params = [];

    // Фильтр по активности
    if ($is_active !== '') {
        $query .= " AND p.is_active = :is_active";
        $params[':is_active'] = intval($is_active);
    }

    // Поиск по названию или описанию
    if (!empty($search)) {
        $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    // Подсчет общего количества
    $count_query = "SELECT COUNT(DISTINCT p.id) as total FROM projects p WHERE 1=1";

    if ($is_active !== '') {
        $count_query .= " AND p.is_active = :is_active";
    }
    if (!empty($search)) {
        $count_query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    }

    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_count = $count_stmt->fetch()['total'];

    // Добавляем сортировку и пагинацию
    $allowed_sort_fields = ['id', 'name', 'created_at', 'updated_at', 'is_active'];
    if (!in_array($sort_by, $allowed_sort_fields)) {
        $sort_by = 'created_at';
    }

    $query .= " ORDER BY p." . $sort_by . " " . $sort_order;
    $query .= " LIMIT :limit OFFSET :offset";

    // Выполняем запрос
    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $projects = $stmt->fetchAll();

    // Формируем ответ
    $response = [
        "success" => true,
        "data" => $projects,
        "pagination" => [
            "total" => intval($total_count),
            "page" => $page,
            "per_page" => $per_page,
            "total_pages" => ceil($total_count / $per_page)
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Получить детали проекта по ID
 */
function getProjectDetails($db, $project_id) {
    // Основная информация о проекте
    $query = "SELECT
        p.id,
        p.name,
        p.description,
        p.is_active,
        p.created_at,
        p.updated_at,
        pb.stt_balance_seconds,
        pb.ai_balance_seconds,
        pb.stt_total_used_seconds,
        pb.ai_total_used_seconds,
        pb.last_usage_at,
        pas.is_configured,
        pas.auto_transcription,
        pas.auto_analysis,
        pas.auto_diarization,
        pas.llm_provider,
        pas.llm_model,
        pas.analysis_prompt_version,
        pas.custom_prompts
    FROM projects p
    LEFT JOIN project_balances pb ON p.id = pb.project_id
    LEFT JOIN project_ai_settings pas ON p.id = pas.project_id
    WHERE p.id = :project_id
    LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->execute();

    $project = $stmt->fetch();

    if (!$project) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Project not found"]);
        exit();
    }

    // Получаем интеграции
    $integrations_query = "SELECT
        id,
        integration_type,
        provider_name,
        config,
        webhook_url,
        status,
        is_active,
        last_sync_at,
        created_at,
        updated_at
    FROM project_integrations
    WHERE project_id = :project_id
    ORDER BY created_at";

    $stmt = $db->prepare($integrations_query);
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->execute();
    $project['integrations'] = $stmt->fetchAll();

    // Получаем чек-листы
    $checklists_query = "SELECT
        id,
        name,
        type,
        items,
        is_active,
        created_at,
        updated_at
    FROM project_checklists
    WHERE project_id = :project_id
    ORDER BY created_at";

    $stmt = $db->prepare($checklists_query);
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->execute();
    $project['checklists'] = $stmt->fetchAll();

    // Получаем AI конфигурации
    $ai_configs_query = "SELECT
        id,
        name,
        checklist_ids,
        date_from,
        date_to,
        duration_from,
        duration_to,
        direction,
        first_call_filter,
        is_active,
        created_at,
        updated_at
    FROM ai_configurations
    WHERE project_id = :project_id
    ORDER BY created_at DESC";

    $stmt = $db->prepare($ai_configs_query);
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->execute();
    $project['ai_configurations'] = $stmt->fetchAll();

    // Статистика звонков
    $stats_query = "SELECT
        COUNT(*) as total_calls,
        COUNT(DISTINCT client_phone) as unique_clients,
        SUM(CASE WHEN is_first_call = 1 THEN 1 ELSE 0 END) as first_calls,
        SUM(duration_sec) as total_duration_sec,
        AVG(duration_sec) as avg_duration_sec
    FROM calls_raw
    WHERE project_id = :project_id";

    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->execute();
    $project['statistics'] = $stmt->fetch();

    $response = [
        "success" => true,
        "data" => $project
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Создать новый проект
 */
function createProject($db) {
    // Получаем данные из тела запроса
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
        exit();
    }

    // Валидация обязательных полей
    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Project name is required"]);
        exit();
    }

    try {
        $db->beginTransaction();

        // Создаем проект
        $name = $input['name'];
        $description = $input['description'] ?? null;
        $is_active = $input['is_active'] ?? 1;

        $query = "INSERT INTO projects (name, description, is_active)
                  VALUES (:name, :description, :is_active)";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        $stmt->execute();

        $project_id = $db->lastInsertId();

        // Создаем дефолтные балансы
        $stt_balance = $input['stt_balance_seconds'] ?? 0;
        $ai_balance = $input['ai_balance_seconds'] ?? 0;

        $balance_query = "INSERT INTO project_balances (project_id, stt_balance_seconds, ai_balance_seconds)
                          VALUES (:project_id, :stt_balance, :ai_balance)";

        $stmt = $db->prepare($balance_query);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->bindParam(':stt_balance', $stt_balance, PDO::PARAM_INT);
        $stmt->bindParam(':ai_balance', $ai_balance, PDO::PARAM_INT);
        $stmt->execute();

        // Создаем дефолтные AI настройки
        $ai_settings_query = "INSERT INTO project_ai_settings (
            project_id,
            is_configured,
            auto_transcription,
            auto_analysis,
            auto_diarization,
            llm_provider,
            llm_model,
            analysis_prompt_version
        ) VALUES (
            :project_id,
            :is_configured,
            :auto_transcription,
            :auto_analysis,
            :auto_diarization,
            :llm_provider,
            :llm_model,
            :analysis_prompt_version
        )";

        $is_configured = $input['is_configured'] ?? 0;
        $auto_transcription = $input['auto_transcription'] ?? 1;
        $auto_analysis = $input['auto_analysis'] ?? 1;
        $auto_diarization = $input['auto_diarization'] ?? 1;
        $llm_provider = $input['llm_provider'] ?? 'openai';
        $llm_model = $input['llm_model'] ?? 'gpt-4o-mini';
        $analysis_prompt_version = $input['analysis_prompt_version'] ?? 'v4';

        $stmt = $db->prepare($ai_settings_query);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->bindParam(':is_configured', $is_configured, PDO::PARAM_INT);
        $stmt->bindParam(':auto_transcription', $auto_transcription, PDO::PARAM_INT);
        $stmt->bindParam(':auto_analysis', $auto_analysis, PDO::PARAM_INT);
        $stmt->bindParam(':auto_diarization', $auto_diarization, PDO::PARAM_INT);
        $stmt->bindParam(':llm_provider', $llm_provider);
        $stmt->bindParam(':llm_model', $llm_model);
        $stmt->bindParam(':analysis_prompt_version', $analysis_prompt_version);
        $stmt->execute();

        // Создаём выбранные чек-листы (если указаны)
        if (!empty($input['checklists']) && is_array($input['checklists'])) {
            $checklist_templates = [
                1 => ['name' => 'Чек-лист 1: Приветствие и представление', 'type' => 'sales_stages'],
                2 => ['name' => 'Чек-лист 2: Выявление потребностей', 'type' => 'sales_stages'],
                3 => ['name' => 'Чек-лист 3: Презентация продукта', 'type' => 'sales_stages'],
                4 => ['name' => 'Чек-лист 4: Работа с возражениями', 'type' => 'sales_stages'],
                5 => ['name' => 'Чек-лист 5: Завершение звонка', 'type' => 'sales_stages'],
                6 => ['name' => 'Чек-лист 6: Соблюдение регламента', 'type' => 'quality_control']
            ];

            $checklist_query = "INSERT INTO project_checklists (project_id, name, type, items, is_active)
                                VALUES (:project_id, :name, :type, :items, 1)";
            $stmt = $db->prepare($checklist_query);

            foreach ($input['checklists'] as $checklist_id) {
                if (isset($checklist_templates[$checklist_id])) {
                    $template = $checklist_templates[$checklist_id];
                    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
                    $stmt->bindParam(':name', $template['name']);
                    $stmt->bindParam(':type', $template['type']);
                    $stmt->bindValue(':items', '[]'); // Пустой JSON массив, можно заполнить позже
                    $stmt->execute();
                }
            }
        }

        $db->commit();

        // Возвращаем созданный проект
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Project created successfully",
            "project_id" => $project_id,
            "data" => ["id" => $project_id] // Для совместимости с frontend
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (PDOException $e) {
        $db->rollBack();

        // Проверка на дублирование имени
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(["success" => false, "error" => "Project name already exists"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
        }
        exit();
    }
}

/**
 * Обновить проект
 */
function updateProject($db, $project_id) {
    // Получаем данные из тела запроса
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
        exit();
    }

    try {
        $db->beginTransaction();

        // Проверяем существование проекта
        $check_query = "SELECT id FROM projects WHERE id = :project_id LIMIT 1";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->execute();

        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Project not found"]);
            exit();
        }

        // Обновляем основную информацию проекта
        $update_fields = [];
        $params = [':project_id' => $project_id];

        if (isset($input['name'])) {
            $update_fields[] = "name = :name";
            $params[':name'] = $input['name'];
        }
        if (isset($input['description'])) {
            $update_fields[] = "description = :description";
            $params[':description'] = $input['description'];
        }
        if (isset($input['is_active'])) {
            $update_fields[] = "is_active = :is_active";
            $params[':is_active'] = intval($input['is_active']);
        }

        if (!empty($update_fields)) {
            $query = "UPDATE projects SET " . implode(', ', $update_fields) . " WHERE id = :project_id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        }

        // Обновляем балансы (если переданы)
        if (isset($input['stt_balance_seconds']) || isset($input['ai_balance_seconds'])) {
            $balance_fields = [];
            $balance_params = [':project_id' => $project_id];

            if (isset($input['stt_balance_seconds'])) {
                $balance_fields[] = "stt_balance_seconds = :stt_balance";
                $balance_params[':stt_balance'] = intval($input['stt_balance_seconds']);
            }
            if (isset($input['ai_balance_seconds'])) {
                $balance_fields[] = "ai_balance_seconds = :ai_balance";
                $balance_params[':ai_balance'] = intval($input['ai_balance_seconds']);
            }

            $balance_query = "UPDATE project_balances SET " . implode(', ', $balance_fields) . " WHERE project_id = :project_id";
            $stmt = $db->prepare($balance_query);
            $stmt->execute($balance_params);
        }

        // Обновляем AI настройки (если переданы)
        $ai_fields_map = [
            'is_configured' => 'is_configured',
            'auto_transcription' => 'auto_transcription',
            'auto_analysis' => 'auto_analysis',
            'auto_diarization' => 'auto_diarization',
            'llm_provider' => 'llm_provider',
            'llm_model' => 'llm_model',
            'analysis_prompt_version' => 'analysis_prompt_version',
            'custom_prompts' => 'custom_prompts'
        ];

        $ai_fields = [];
        $ai_params = [':project_id' => $project_id];

        foreach ($ai_fields_map as $input_key => $db_field) {
            if (isset($input[$input_key])) {
                $ai_fields[] = "$db_field = :$input_key";
                $ai_params[":$input_key"] = $input[$input_key];
            }
        }

        if (!empty($ai_fields)) {
            $ai_query = "UPDATE project_ai_settings SET " . implode(', ', $ai_fields) . " WHERE project_id = :project_id";
            $stmt = $db->prepare($ai_query);
            $stmt->execute($ai_params);
        }

        $db->commit();

        echo json_encode([
            "success" => true,
            "message" => "Project updated successfully"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (PDOException $e) {
        $db->rollBack();

        // Проверка на дублирование имени
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(["success" => false, "error" => "Project name already exists"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
        }
        exit();
    }
}

/**
 * Удалить проект
 */
function deleteProject($db, $project_id) {
    try {
        // Проверяем, что это не проект Домрил (id = 1)
        if ($project_id == 1) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Cannot delete default project 'Домрил'"]);
            exit();
        }

        // Проверяем существование проекта
        $check_query = "SELECT id, name FROM projects WHERE id = :project_id LIMIT 1";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->execute();

        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Project not found"]);
            exit();
        }

        // Проверяем, есть ли звонки в этом проекте
        $calls_check = "SELECT COUNT(*) as count FROM calls_raw WHERE project_id = :project_id";
        $stmt = $db->prepare($calls_check);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->execute();
        $calls_count = $stmt->fetch()['count'];

        if ($calls_count > 0) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "error" => "Cannot delete project with existing calls. Found $calls_count calls."
            ]);
            exit();
        }

        // Удаляем проект (CASCADE удалит связанные записи)
        $delete_query = "DELETE FROM projects WHERE id = :project_id";
        $stmt = $db->prepare($delete_query);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            "success" => true,
            "message" => "Project '{$project['name']}' deleted successfully"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
        exit();
    }
}
