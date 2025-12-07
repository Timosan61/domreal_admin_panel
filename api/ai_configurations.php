<?php
/**
 * API для управления AI конфигурациями проекта
 *
 * Endpoints:
 * - GET ?project_id=X - Получить конфигурации ИИ проекта
 * - POST - Создать новую конфигурацию
 * - PUT ?id=X - Обновить конфигурацию
 * - DELETE ?id=X - Удалить конфигурацию
 * - POST ?id=X&action=start - Запустить оценку ИИ для проекта
 * - POST ?id=X&action=stop - Остановить оценку ИИ для проекта
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
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Обработка preflight запроса (OPTIONS)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * GET - Получить AI настройки проекта
 */
if ($method === 'GET') {
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

    if (!$project_id) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан project_id"
        ]);
        exit();
    }

    // Получаем AI настройки проекта
    $query = "SELECT
        pas.id,
        pas.project_id,
        p.name as project_name,
        pas.is_configured,
        pas.auto_transcription,
        pas.auto_analysis,
        pas.auto_diarization,
        pas.llm_provider,
        pas.llm_model,
        pas.analysis_prompt_version,
        pas.custom_prompts,
        pas.created_at,
        pas.updated_at
    FROM project_ai_settings pas
    JOIN projects p ON pas.project_id = p.id
    WHERE pas.project_id = :project_id
    LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);

    try {
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($settings) {
            // Парсим JSON поля
            if ($settings['custom_prompts']) {
                $settings['custom_prompts'] = json_decode($settings['custom_prompts'], true);
            }

            echo json_encode([
                "success" => true,
                "data" => $settings
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "AI настройки для проекта не найдены"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка запроса: " . $e->getMessage()
        ]);
    }
    exit();
}

/**
 * POST - Создать новую конфигурацию или выполнить действие
 */
if ($method === 'POST') {
    // Проверяем, есть ли параметр action (для start/stop)
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $action = isset($_GET['action']) ? $_GET['action'] : null;

    // Обработка действий start/stop
    if ($id && $action) {
        if ($action === 'start') {
            // Запускаем оценку ИИ для проекта
            $query = "UPDATE project_ai_settings
                      SET auto_analysis = TRUE,
                          updated_at = NOW()
                      WHERE id = :id";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            try {
                $stmt->execute();
                echo json_encode([
                    "success" => true,
                    "message" => "Оценка ИИ запущена для проекта"
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "error" => "Ошибка запуска: " . $e->getMessage()
                ]);
            }
            exit();

        } elseif ($action === 'stop') {
            // Останавливаем оценку ИИ для проекта
            $query = "UPDATE project_ai_settings
                      SET auto_analysis = FALSE,
                          updated_at = NOW()
                      WHERE id = :id";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            try {
                $stmt->execute();
                echo json_encode([
                    "success" => true,
                    "message" => "Оценка ИИ остановлена для проекта"
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "error" => "Ошибка остановки: " . $e->getMessage()
                ]);
            }
            exit();

        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Неизвестное действие: " . $action
            ]);
            exit();
        }
    }

    // Создание новой конфигурации
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Некорректные данные запроса"
        ]);
        exit();
    }

    // Валидация обязательных полей
    if (!isset($data['project_id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан project_id"
        ]);
        exit();
    }

    // Проверяем, существует ли проект
    $check_query = "SELECT id FROM projects WHERE id = :project_id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':project_id', $data['project_id'], PDO::PARAM_INT);
    $check_stmt->execute();

    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Проект не найден"
        ]);
        exit();
    }

    // Значения по умолчанию
    $defaults = [
        'is_configured' => true,
        'auto_transcription' => true,
        'auto_analysis' => true,
        'auto_diarization' => true,
        'llm_provider' => 'openai',
        'llm_model' => 'gpt-4o-mini',
        'analysis_prompt_version' => 'v4',
        'custom_prompts' => null
    ];

    $insert_data = array_merge($defaults, $data);

    // Обработка JSON полей
    if (isset($insert_data['custom_prompts']) && is_array($insert_data['custom_prompts'])) {
        $insert_data['custom_prompts'] = json_encode($insert_data['custom_prompts'], JSON_UNESCAPED_UNICODE);
    }

    // Вставка новой конфигурации
    $query = "INSERT INTO project_ai_settings (
        project_id,
        is_configured,
        auto_transcription,
        auto_analysis,
        auto_diarization,
        llm_provider,
        llm_model,
        analysis_prompt_version,
        custom_prompts
    ) VALUES (
        :project_id,
        :is_configured,
        :auto_transcription,
        :auto_analysis,
        :auto_diarization,
        :llm_provider,
        :llm_model,
        :analysis_prompt_version,
        :custom_prompts
    )";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':project_id', $insert_data['project_id'], PDO::PARAM_INT);
    $stmt->bindValue(':is_configured', $insert_data['is_configured'], PDO::PARAM_BOOL);
    $stmt->bindValue(':auto_transcription', $insert_data['auto_transcription'], PDO::PARAM_BOOL);
    $stmt->bindValue(':auto_analysis', $insert_data['auto_analysis'], PDO::PARAM_BOOL);
    $stmt->bindValue(':auto_diarization', $insert_data['auto_diarization'], PDO::PARAM_BOOL);
    $stmt->bindValue(':llm_provider', $insert_data['llm_provider']);
    $stmt->bindValue(':llm_model', $insert_data['llm_model']);
    $stmt->bindValue(':analysis_prompt_version', $insert_data['analysis_prompt_version']);
    $stmt->bindValue(':custom_prompts', $insert_data['custom_prompts']);

    try {
        $stmt->execute();
        $new_id = $db->lastInsertId();

        echo json_encode([
            "success" => true,
            "message" => "AI конфигурация создана",
            "id" => $new_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка создания: " . $e->getMessage()
        ]);
    }
    exit();
}

/**
 * PUT - Обновить конфигурацию
 */
if ($method === 'PUT') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан ID конфигурации"
        ]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Некорректные данные запроса"
        ]);
        exit();
    }

    // Обработка JSON полей
    if (isset($data['custom_prompts']) && is_array($data['custom_prompts'])) {
        $data['custom_prompts'] = json_encode($data['custom_prompts'], JSON_UNESCAPED_UNICODE);
    }

    // Формируем SQL для обновления только переданных полей
    $allowed_fields = [
        'is_configured', 'auto_transcription', 'auto_analysis',
        'auto_diarization', 'llm_provider', 'llm_model',
        'analysis_prompt_version', 'custom_prompts'
    ];

    $update_fields = [];
    $params = [':id' => $id];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
            $update_fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }

    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Нет полей для обновления"
        ]);
        exit();
    }

    $query = "UPDATE project_ai_settings
              SET " . implode(', ', $update_fields) . ", updated_at = NOW()
              WHERE id = :id";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        if ($key === ':id') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } elseif (in_array(str_replace(':', '', $key), ['is_configured', 'auto_transcription', 'auto_analysis', 'auto_diarization'])) {
            $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    try {
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "AI конфигурация обновлена"
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Конфигурация не найдена или данные не изменились"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка обновления: " . $e->getMessage()
        ]);
    }
    exit();
}

/**
 * DELETE - Удалить конфигурацию
 */
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан ID конфигурации"
        ]);
        exit();
    }

    $query = "DELETE FROM project_ai_settings WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    try {
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "AI конфигурация удалена"
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Конфигурация не найдена"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка удаления: " . $e->getMessage()
        ]);
    }
    exit();
}

// Неподдерживаемый метод
http_response_code(405);
echo json_encode([
    "success" => false,
    "error" => "Метод не поддерживается"
]);
