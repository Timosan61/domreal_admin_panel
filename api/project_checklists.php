<?php
/**
 * API для управления чек-листами проектов
 * GET /api/project_checklists.php - получить список чек-листов
 * GET /api/project_checklists.php?project_id=X - чек-листы конкретного проекта
 * GET /api/project_checklists.php?available_types=1 - список доступных типов чек-листов
 * POST /api/project_checklists.php - создать новый чек-лист
 * PUT /api/project_checklists.php?id=X - обновить чек-лист
 * DELETE /api/project_checklists.php?id=X - удалить чек-лист
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
    // Если запрошены доступные типы чек-листов
    if (isset($_GET['available_types'])) {
        $available_types = [
            [
                'value' => 'sales_stages',
                'label' => 'Этапы продаж',
                'description' => 'Чек-лист для отслеживания ключевых этапов процесса продаж'
            ],
            [
                'value' => 'ai_evaluation',
                'label' => 'Оценка ИИ',
                'description' => 'Чек-лист для AI-анализа качества звонков и коммуникации'
            ],
            [
                'value' => 'quality_control',
                'label' => 'Контроль качества',
                'description' => 'Чек-лист для ручной оценки качества работы менеджеров'
            ],
            [
                'value' => 'custom',
                'label' => 'Пользовательский',
                'description' => 'Настраиваемый чек-лист для специфических бизнес-процессов'
            ]
        ];

        echo json_encode([
            "success" => true,
            "data" => $available_types
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    // Получение списка чек-листов
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $type = isset($_GET['type']) ? $_GET['type'] : null;

    $query = "SELECT
        pc.id,
        pc.project_id,
        pc.name,
        pc.type,
        pc.items,
        pc.is_active,
        pc.created_at,
        pc.updated_at,
        p.name as project_name
    FROM project_checklists pc
    LEFT JOIN projects p ON pc.project_id = p.id
    WHERE 1=1";

    $params = [];

    // Фильтр по project_id
    if ($project_id !== null) {
        $query .= " AND pc.project_id = :project_id";
        $params[':project_id'] = $project_id;
    }

    // Фильтр по id (получение одного чек-листа)
    if ($id !== null) {
        $query .= " AND pc.id = :id";
        $params[':id'] = $id;
    }

    // Фильтр по типу
    if ($type !== null) {
        $query .= " AND pc.type = :type";
        $params[':type'] = $type;
    }

    // Проверка прав доступа (только admin может видеть все)
    if ($_SESSION['role'] !== 'admin') {
        // Обычные пользователи могут видеть только чек-листы своих проектов
        // TODO: Добавить таблицу project_users для привязки пользователей к проектам
        // Пока разрешаем всем авторизованным пользователям
    }

    $query .= " ORDER BY pc.created_at DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $checklists = $stmt->fetchAll();

    // Декодируем JSON items для каждого чек-листа и считаем статистику
    foreach ($checklists as &$checklist) {
        if ($checklist['items']) {
            $checklist['items'] = json_decode($checklist['items'], true);
            $checklist['items_count'] = count($checklist['items']);

            // Считаем общий вес (если указан)
            $total_weight = 0;
            foreach ($checklist['items'] as $item) {
                if (isset($item['weight'])) {
                    $total_weight += intval($item['weight']);
                }
            }
            $checklist['total_weight'] = $total_weight;
        } else {
            $checklist['items'] = [];
            $checklist['items_count'] = 0;
            $checklist['total_weight'] = 0;
        }
    }

    if ($id !== null && count($checklists) === 1) {
        // Возвращаем один чек-лист
        echo json_encode([
            "success" => true,
            "data" => $checklists[0]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        // Возвращаем список
        echo json_encode([
            "success" => true,
            "data" => $checklists,
            "count" => count($checklists)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit();
}

// Обработка POST запросов (создание нового чек-листа)
if ($method === 'POST') {
    // Только admin может создавать чек-листы
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
    if (!isset($data['project_id']) || !isset($data['name']) || !isset($data['type'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Обязательные поля: project_id, name, type"
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

    // Валидация type
    $allowed_types = ['sales_stages', 'ai_evaluation', 'quality_control', 'custom'];
    if (!in_array($data['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Недопустимый тип чек-листа. Разрешены: " . implode(', ', $allowed_types)
        ]);
        exit();
    }

    // Валидация items (должен быть массив)
    if (isset($data['items'])) {
        if (!is_array($data['items'])) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Поле items должно быть массивом"
            ]);
            exit();
        }

        // Проверяем структуру каждого item
        foreach ($data['items'] as $item) {
            if (!isset($item['text'])) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "error" => "Каждый элемент items должен содержать поле 'text'"
                ]);
                exit();
            }
        }
    }

    // Подготовка items (если передан как массив, конвертируем в JSON)
    $items = isset($data['items']) ? json_encode($data['items'], JSON_UNESCAPED_UNICODE) : null;

    // Вставка нового чек-листа
    $query = "INSERT INTO project_checklists
        (project_id, name, type, items, is_active)
        VALUES (:project_id, :name, :type, :items, :is_active)";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':project_id', $data['project_id']);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':type', $data['type']);
    $stmt->bindValue(':items', $items);
    $stmt->bindValue(':is_active', isset($data['is_active']) ? $data['is_active'] : true, PDO::PARAM_BOOL);

    if ($stmt->execute()) {
        $new_id = $db->lastInsertId();

        // Получаем созданный чек-лист
        $select_query = "SELECT * FROM project_checklists WHERE id = :id LIMIT 1";
        $select_stmt = $db->prepare($select_query);
        $select_stmt->bindValue(':id', $new_id);
        $select_stmt->execute();
        $new_checklist = $select_stmt->fetch();

        // Декодируем items
        if ($new_checklist['items']) {
            $new_checklist['items'] = json_decode($new_checklist['items'], true);
        }

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Чек-лист успешно создан",
            "data" => $new_checklist
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка при создании чек-листа"
        ]);
    }
    exit();
}

// Обработка PUT запросов (обновление чек-листа)
if ($method === 'PUT') {
    // Только admin может обновлять чек-листы
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
            "error" => "Не указан ID чек-листа"
        ]);
        exit();
    }

    // Получаем данные из тела запроса
    $data = json_decode(file_get_contents("php://input"), true);

    // Проверяем, что чек-лист существует
    $check_query = "SELECT id FROM project_checklists WHERE id = :id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':id', $id);
    $check_stmt->execute();
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Чек-лист с ID {$id} не найден"
        ]);
        exit();
    }

    // Формируем SQL для обновления только переданных полей
    $update_fields = [];
    $params = [':id' => $id];

    if (isset($data['name'])) {
        $update_fields[] = "name = :name";
        $params[':name'] = $data['name'];
    }

    if (isset($data['type'])) {
        $allowed_types = ['sales_stages', 'ai_evaluation', 'quality_control', 'custom'];
        if (!in_array($data['type'], $allowed_types)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Недопустимый тип чек-листа"
            ]);
            exit();
        }
        $update_fields[] = "type = :type";
        $params[':type'] = $data['type'];
    }

    if (isset($data['items'])) {
        if (!is_array($data['items'])) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Поле items должно быть массивом"
            ]);
            exit();
        }
        $update_fields[] = "items = :items";
        $params[':items'] = json_encode($data['items'], JSON_UNESCAPED_UNICODE);
    }

    if (isset($data['is_active'])) {
        $update_fields[] = "is_active = :is_active";
        $params[':is_active'] = $data['is_active'];
    }

    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указаны поля для обновления"
        ]);
        exit();
    }

    $query = "UPDATE project_checklists SET " . implode(', ', $update_fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if ($stmt->execute()) {
        // Получаем обновленный чек-лист
        $select_query = "SELECT * FROM project_checklists WHERE id = :id LIMIT 1";
        $select_stmt = $db->prepare($select_query);
        $select_stmt->bindValue(':id', $id);
        $select_stmt->execute();
        $updated_checklist = $select_stmt->fetch();

        // Декодируем items
        if ($updated_checklist['items']) {
            $updated_checklist['items'] = json_decode($updated_checklist['items'], true);
        }

        echo json_encode([
            "success" => true,
            "message" => "Чек-лист успешно обновлен",
            "data" => $updated_checklist
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка при обновлении чек-листа"
        ]);
    }
    exit();
}

// Обработка DELETE запросов (удаление чек-листа)
if ($method === 'DELETE') {
    // Только admin может удалять чек-листы
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
            "error" => "Не указан ID чек-листа"
        ]);
        exit();
    }

    // Проверяем, что чек-лист существует
    $check_query = "SELECT id FROM project_checklists WHERE id = :id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':id', $id);
    $check_stmt->execute();
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Чек-лист с ID {$id} не найден"
        ]);
        exit();
    }

    // Удаляем чек-лист
    $query = "DELETE FROM project_checklists WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Чек-лист успешно удален"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка при удалении чек-листа"
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
