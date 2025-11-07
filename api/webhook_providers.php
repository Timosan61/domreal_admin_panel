<?php
/**
 * API для управления поставщиками webhook
 *
 * Доступные действия:
 * - list: Получить список всех поставщиков
 * - get: Получить данные одного поставщика по ID
 * - add: Добавить нового поставщика
 * - update: Обновить существующего поставщика
 * - toggle: Включить/выключить поставщика
 * - delete: Удалить поставщика
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// Проверка авторизации
require_once '../auth/session.php';
try {
    checkAuth($require_admin = true);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подключение к БД
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? null;

try {
    switch ($action) {
        case 'list':
            // Получить список всех поставщиков
            $query = "
                SELECT
                    id,
                    provider_code,
                    provider_name,
                    google_sheets_id,
                    is_active,
                    notes,
                    created_at,
                    updated_at
                FROM webhook_providers
                ORDER BY id ASC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute();
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convert tinyint to boolean
            foreach ($providers as &$provider) {
                $provider['is_active'] = (bool)$provider['is_active'];
            }

            echo json_encode([
                'success' => true,
                'providers' => $providers
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'get':
            // Получить данные одного поставщика
            $id = intval($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Неверный ID поставщика');
            }

            $query = "
                SELECT
                    id,
                    provider_code,
                    provider_name,
                    google_sheets_id,
                    is_active,
                    notes,
                    created_at,
                    updated_at
                FROM webhook_providers
                WHERE id = ?
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $provider = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$provider) {
                throw new Exception('Поставщик не найден');
            }

            $provider['is_active'] = (bool)$provider['is_active'];

            echo json_encode([
                'success' => true,
                'provider' => $provider
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'add':
            // Добавить нового поставщика
            $input = json_decode(file_get_contents('php://input'), true);

            $code = trim($input['provider_code'] ?? '');
            $name = trim($input['provider_name'] ?? '');
            $sheetsId = trim($input['google_sheets_id'] ?? '');
            $notes = trim($input['notes'] ?? '');

            if (empty($code) || empty($name)) {
                throw new Exception('Код и название поставщика обязательны');
            }

            // Проверяем уникальность кода
            $check_query = "SELECT COUNT(*) FROM webhook_providers WHERE provider_code = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$code]);

            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('Поставщик с таким кодом уже существует');
            }

            // Создаём базовый маппинг (будет настраиваться позже)
            $defaultFieldMapping = json_encode([
                'date' => '$.date',
                'phone' => '$.phone'
            ]);

            $defaultColumnOrder = json_encode([
                'Дата', 'ID', 'Телефон', 'Email'
            ]);

            $query = "
                INSERT INTO webhook_providers (
                    provider_code,
                    provider_name,
                    google_sheets_id,
                    field_mapping,
                    column_order,
                    notes,
                    is_active,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                $code,
                $name,
                $sheetsId ?: null,
                $defaultFieldMapping,
                $defaultColumnOrder,
                $notes ?: null
            ]);

            $newId = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Поставщик добавлен',
                'id' => $newId
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'update':
            // Обновить существующего поставщика
            $input = json_decode(file_get_contents('php://input'), true);

            $id = intval($input['id'] ?? 0);
            $code = trim($input['provider_code'] ?? '');
            $name = trim($input['provider_name'] ?? '');
            $sheetsId = trim($input['google_sheets_id'] ?? '');
            $notes = trim($input['notes'] ?? '');

            if ($id <= 0) {
                throw new Exception('Неверный ID поставщика');
            }

            if (empty($code) || empty($name)) {
                throw new Exception('Код и название поставщика обязательны');
            }

            // Проверяем уникальность кода (исключая текущую запись)
            $check_query = "SELECT COUNT(*) FROM webhook_providers WHERE provider_code = ? AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$code, $id]);

            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('Поставщик с таким кодом уже существует');
            }

            $query = "
                UPDATE webhook_providers
                SET
                    provider_code = ?,
                    provider_name = ?,
                    google_sheets_id = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                $code,
                $name,
                $sheetsId ?: null,
                $notes ?: null,
                $id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Поставщик обновлён'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'toggle':
            // Включить/выключить поставщика
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Неверный ID поставщика');
            }

            $query = "
                UPDATE webhook_providers
                SET is_active = NOT is_active,
                    updated_at = NOW()
                WHERE id = ?
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Статус поставщика изменён'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            // Удалить поставщика
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Неверный ID поставщика');
            }

            // Проверяем, что это не GCK (защита)
            $check_query = "SELECT provider_code FROM webhook_providers WHERE id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$id]);
            $code = $check_stmt->fetchColumn();

            if ($code === 'gck') {
                throw new Exception('Нельзя удалить системного поставщика GCK');
            }

            $query = "DELETE FROM webhook_providers WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Поставщик удалён'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
