<?php
/**
 * API для управления интеграциями проектов
 * GET /api/project_integrations.php - получить список интеграций
 * GET /api/project_integrations.php?project_id=X - интеграции конкретного проекта
 * GET /api/project_integrations.php?available_types=1 - список доступных типов интеграций
 * POST /api/project_integrations.php - создать новую интеграцию
 * PUT /api/project_integrations.php?id=X - обновить интеграцию
 * DELETE /api/project_integrations.php?id=X - удалить интеграцию
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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

// Обработка GET запросов
if ($method === 'GET') {
    // Если запрошены доступные типы интеграций
    if (isset($_GET['available_types'])) {
        $available_types = [
            [
                'value' => 'crm',
                'label' => 'CRM система',
                'providers' => ['JoyWork', 'amoCRM', 'Bitrix24', 'RetailCRM']
            ],
            [
                'value' => 'telephony',
                'label' => 'Телефония',
                'providers' => ['Beeline', 'МТС', 'Ростелеком', 'Zadarma']
            ],
            [
                'value' => 'google_drive',
                'label' => 'Хранилище файлов',
                'providers' => ['Google Drive', 'Яндекс.Диск', 'Dropbox']
            ],
            [
                'value' => 'other',
                'label' => 'Другое',
                'providers' => ['Custom API', 'Webhook']
            ]
        ];

        echo json_encode([
            "success" => true,
            "data" => $available_types
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    // Получение списка интеграций
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    $query = "SELECT
        pi.id,
        pi.project_id,
        pi.integration_type,
        pi.provider_name,
        pi.config,
        pi.is_active,
        pi.last_sync_at,
        pi.created_at,
        pi.updated_at,
        p.name as project_name
    FROM project_integrations pi
    LEFT JOIN projects p ON pi.project_id = p.id
    WHERE 1=1";

    $params = [];

    // Фильтр по project_id
    if ($project_id !== null) {
        $query .= " AND pi.project_id = :project_id";
        $params[':project_id'] = $project_id;
    }

    // Фильтр по id (получение одной интеграции)
    if ($id !== null) {
        $query .= " AND pi.id = :id";
        $params[':id'] = $id;
    }

    // Проверка прав доступа (только admin может видеть все)
    if ($_SESSION['role'] !== 'admin') {
        // Обычные пользователи могут видеть только интеграции своих проектов
        // TODO: Добавить таблицу project_users для привязки пользователей к проектам
        // Пока разрешаем всем авторизованным пользователям
    }

    $query .= " ORDER BY pi.created_at DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $integrations = $stmt->fetchAll();

    // Декодируем JSON config для каждой интеграции
    foreach ($integrations as &$integration) {
        if ($integration['config']) {
            $integration['config'] = json_decode($integration['config'], true);
        }
    }

    if ($id !== null && count($integrations) === 1) {
        // Возвращаем одну интеграцию
        echo json_encode([
            "success" => true,
            "data" => $integrations[0]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        // Возвращаем список
        echo json_encode([
            "success" => true,
            "data" => $integrations,
            "count" => count($integrations)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit();
}

// Обработка POST запросов (создание новой интеграции)
if ($method === 'POST') {
    // Только admin может создавать интеграции
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "Доступ запрещен. Требуются права администратора."
        ]);
        exit();
    }

    // Получаем данные из тела запроса
    $data = json_decode(file_get_contents("php://input"), true);

    // Валидация обязательных полей
    if (!isset($data['project_id']) || !isset($data['integration_type']) || !isset($data['provider_name'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Обязательные поля: project_id, integration_type, provider_name"
        ]);
        exit();
    }

    // Проверяем, что проект существует
    $check_query = "SELECT id FROM projects WHERE id = :project_id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':project_id', $data['project_id']);
    $check_stmt->execute();
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Проект с ID {$data['project_id']} не найден"
        ]);
        exit();
    }

    // Валидация integration_type
    $allowed_types = ['crm', 'telephony', 'google_drive', 'other'];
    if (!in_array($data['integration_type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Недопустимый тип интеграции. Разрешены: " . implode(', ', $allowed_types)
        ]);
        exit();
    }

    // Подготовка config (если передан как массив, конвертируем в JSON)
    $config = isset($data['config']) ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : null;

    // Вставка новой интеграции
    $query = "INSERT INTO project_integrations
        (project_id, integration_type, provider_name, config, is_active)
        VALUES (:project_id, :integration_type, :provider_name, :config, :is_active)";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':project_id', $data['project_id']);
    $stmt->bindValue(':integration_type', $data['integration_type']);
    $stmt->bindValue(':provider_name', $data['provider_name']);
    $stmt->bindValue(':config', $config);
    $stmt->bindValue(':is_active', isset($data['is_active']) ? $data['is_active'] : true, PDO::PARAM_BOOL);

    if ($stmt->execute()) {
        $new_id = $db->lastInsertId();

        // Получаем созданную интеграцию
        $select_query = "SELECT * FROM project_integrations WHERE id = :id LIMIT 1";
        $select_stmt = $db->prepare($select_query);
        $select_stmt->bindValue(':id', $new_id);
        $select_stmt->execute();
        $new_integration = $select_stmt->fetch();

        // Декодируем config
        if ($new_integration['config']) {
            $new_integration['config'] = json_decode($new_integration['config'], true);
        }

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Интеграция успешно создана",
            "data" => $new_integration
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка при создании интеграции"
        ]);
    }
    exit();
}

// Обработка PUT запросов (обновление интеграции)
if ($method === 'PUT') {
    // Только admin может обновлять интеграции
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "Доступ запрещен. Требуются права администратора."
        ]);
        exit();
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if ($id === null) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан ID интеграции"
        ]);
        exit();
    }

    // Получаем данные из тела запроса
    $data = json_decode(file_get_contents("php://input"), true);

    // Проверяем, что интеграция существует
    $check_query = "SELECT id FROM project_integrations WHERE id = :id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':id', $id);
    $check_stmt->execute();
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Интеграция с ID {$id} не найдена"
        ]);
        exit();
    }

    // Формируем SQL для обновления только переданных полей
    $update_fields = [];
    $params = [':id' => $id];

    if (isset($data['integration_type'])) {
        $allowed_types = ['crm', 'telephony', 'google_drive', 'other'];
        if (!in_array($data['integration_type'], $allowed_types)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Недопустимый тип интеграции"
            ]);
            exit();
        }
        $update_fields[] = "integration_type = :integration_type";
        $params[':integration_type'] = $data['integration_type'];
    }

    if (isset($data['provider_name'])) {
        $update_fields[] = "provider_name = :provider_name";
        $params[':provider_name'] = $data['provider_name'];
    }

    if (isset($data['config'])) {
        $update_fields[] = "config = :config";
        $params[':config'] = json_encode($data['config'], JSON_UNESCAPED_UNICODE);
    }

    if (isset($data['is_active'])) {
        $update_fields[] = "is_active = :is_active";
        $params[':is_active'] = $data['is_active'];
    }

    if (isset($data['last_sync_at'])) {
        $update_fields[] = "last_sync_at = :last_sync_at";
        $params[':last_sync_at'] = $data['last_sync_at'];
    }

    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указаны поля для обновления"
        ]);
        exit();
    }

    $query = "UPDATE project_integrations SET " . implode(', ', $update_fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if ($stmt->execute()) {
        // Получаем обновленную интеграцию
        $select_query = "SELECT * FROM project_integrations WHERE id = :id LIMIT 1";
        $select_stmt = $db->prepare($select_query);
        $select_stmt->bindValue(':id', $id);
        $select_stmt->execute();
        $updated_integration = $select_stmt->fetch();

        // Декодируем config
        if ($updated_integration['config']) {
            $updated_integration['config'] = json_decode($updated_integration['config'], true);
        }

        echo json_encode([
            "success" => true,
            "message" => "Интеграция успешно обновлена",
            "data" => $updated_integration
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка при обновлении интеграции"
        ]);
    }
    exit();
}

// Обработка DELETE запросов (удаление интеграции)
if ($method === 'DELETE') {
    // Только admin может удалять интеграции
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "Доступ запрещен. Требуются права администратора."
        ]);
        exit();
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if ($id === null) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан ID интеграции"
        ]);
        exit();
    }

    // Проверяем, что интеграция существует
    $check_query = "SELECT id FROM project_integrations WHERE id = :id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':id', $id);
    $check_stmt->execute();
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Интеграция с ID {$id} не найдена"
        ]);
        exit();
    }

    // Удаляем интеграцию
    $query = "DELETE FROM project_integrations WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Интеграция успешно удалена"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка при удалении интеграции"
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
