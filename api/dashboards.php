<?php
/**
 * API: Управление дашбордами
 *
 * Методы:
 * - GET     /api/dashboards.php?action=list         - Список дашбордов
 * - GET     /api/dashboards.php?action=get&id=...   - Получить дашборд с виджетами
 * - POST    /api/dashboards.php?action=create       - Создать дашборд
 * - PUT     /api/dashboards.php?action=update&id=.. - Обновить дашборд
 * - DELETE  /api/dashboards.php?action=delete&id=.. - Удалить дашборд
 * - PATCH   /api/dashboards.php?action=set_default&id=.. - Установить дашборд по умолчанию
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $dashboard_id = $_GET['id'] ?? '';

    // ═══════════════════════════════════════════════════════════
    // GET: Список дашбордов
    // ═══════════════════════════════════════════════════════════
    if ($method === 'GET' && $action === 'list') {
        $query = "
            SELECT
                dc.dashboard_id,
                dc.org_id,
                dc.name,
                dc.description,
                dc.is_default,
                dc.is_active,
                dc.layout_type,
                dc.created_by,
                dc.created_at,
                dc.updated_at,
                COUNT(dw.widget_id) AS widgets_count
            FROM dashboard_configs dc
            LEFT JOIN dashboard_widgets dw ON dc.dashboard_id = dw.dashboard_id
            GROUP BY dc.dashboard_id
            ORDER BY dc.is_default DESC, dc.name
        ";

        $stmt = $pdo->query($query);
        $dashboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $dashboards
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    // ═══════════════════════════════════════════════════════════
    // GET: Получить дашборд с виджетами
    // ═══════════════════════════════════════════════════════════
    if ($method === 'GET' && $action === 'get') {
        if (empty($dashboard_id)) {
            // Если ID не указан, получаем дашборд по умолчанию
            $stmt = $pdo->prepare("SELECT dashboard_id FROM dashboard_configs WHERE is_default = 1 AND is_active = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception('No default dashboard found');
            }
            $dashboard_id = $result['dashboard_id'];
        }

        // Получаем информацию о дашборде
        $stmt = $pdo->prepare("SELECT * FROM dashboard_configs WHERE dashboard_id = ?");
        $stmt->execute([$dashboard_id]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dashboard) {
            throw new Exception('Dashboard not found');
        }

        // Получаем виджеты
        $stmt = $pdo->prepare("
            SELECT *
            FROM dashboard_widgets
            WHERE dashboard_id = ?
            ORDER BY widget_order
        ");
        $stmt->execute([$dashboard_id]);
        $widgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Декодируем JSON конфиги
        foreach ($widgets as &$widget) {
            if ($widget['config_json']) {
                $widget['config'] = json_decode($widget['config_json'], true);
                unset($widget['config_json']);
            }
        }

        $dashboard['widgets'] = $widgets;

        echo json_encode([
            'success' => true,
            'data' => $dashboard
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    // ═══════════════════════════════════════════════════════════
    // POST: Создать дашборд
    // ═══════════════════════════════════════════════════════════
    if ($method === 'POST' && $action === 'create') {
        $input = json_decode(file_get_contents('php://input'), true);

        $new_dashboard_id = 'dashboard-' . uniqid();
        $org_id = $input['org_id'] ?? 'org-legacy';
        $name = $input['name'] ?? 'Новый дашборд';
        $description = $input['description'] ?? '';
        $is_default = $input['is_default'] ?? false;
        $layout_type = $input['layout_type'] ?? 'grid';
        $created_by = $input['created_by'] ?? 'user';

        // Если устанавливаем дашборд по умолчанию, снимаем флаг с других
        if ($is_default) {
            $pdo->exec("UPDATE dashboard_configs SET is_default = FALSE WHERE org_id = '$org_id'");
        }

        $stmt = $pdo->prepare("
            INSERT INTO dashboard_configs (
                dashboard_id, org_id, name, description, is_default, layout_type, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $new_dashboard_id, $org_id, $name, $description, $is_default, $layout_type, $created_by
        ]);

        // Если есть виджеты в запросе, добавляем их
        if (!empty($input['widgets'])) {
            foreach ($input['widgets'] as $order => $widget) {
                $widget_id = 'widget-' . uniqid();
                $config_json = json_encode($widget['config'] ?? []);

                $stmt = $pdo->prepare("
                    INSERT INTO dashboard_widgets (
                        widget_id, dashboard_id, widget_type, widget_order,
                        title, data_source, config_json, size_width, size_height
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $widget_id, $new_dashboard_id, $widget['widget_type'], $order,
                    $widget['title'], $widget['data_source'], $config_json,
                    $widget['size_width'] ?? 1, $widget['size_height'] ?? 1
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'dashboard_id' => $new_dashboard_id,
            'message' => 'Dashboard created successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ═══════════════════════════════════════════════════════════
    // PUT: Обновить дашборд
    // ═══════════════════════════════════════════════════════════
    if ($method === 'PUT' && $action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($dashboard_id)) {
            throw new Exception('Dashboard ID is required');
        }

        $name = $input['name'] ?? null;
        $description = $input['description'] ?? null;
        $is_active = $input['is_active'] ?? null;
        $layout_type = $input['layout_type'] ?? null;

        $updates = [];
        $params = [];

        if ($name !== null) {
            $updates[] = "name = ?";
            $params[] = $name;
        }
        if ($description !== null) {
            $updates[] = "description = ?";
            $params[] = $description;
        }
        if ($is_active !== null) {
            $updates[] = "is_active = ?";
            $params[] = $is_active;
        }
        if ($layout_type !== null) {
            $updates[] = "layout_type = ?";
            $params[] = $layout_type;
        }

        if (!empty($updates)) {
            $params[] = $dashboard_id;
            $query = "UPDATE dashboard_configs SET " . implode(', ', $updates) . " WHERE dashboard_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Dashboard updated successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE: Удалить дашборд
    // ═══════════════════════════════════════════════════════════
    if ($method === 'DELETE' && $action === 'delete') {
        if (empty($dashboard_id)) {
            throw new Exception('Dashboard ID is required');
        }

        // Проверяем что это не дашборд по умолчанию
        $stmt = $pdo->prepare("SELECT is_default FROM dashboard_configs WHERE dashboard_id = ?");
        $stmt->execute([$dashboard_id]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dashboard['is_default']) {
            throw new Exception('Cannot delete default dashboard');
        }

        // Удаляем (виджеты удалятся автоматически через CASCADE)
        $stmt = $pdo->prepare("DELETE FROM dashboard_configs WHERE dashboard_id = ?");
        $stmt->execute([$dashboard_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Dashboard deleted successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ═══════════════════════════════════════════════════════════
    // PATCH: Установить дашборд по умолчанию
    // ═══════════════════════════════════════════════════════════
    if ($method === 'PATCH' && $action === 'set_default') {
        if (empty($dashboard_id)) {
            throw new Exception('Dashboard ID is required');
        }

        // Получаем org_id дашборда
        $stmt = $pdo->prepare("SELECT org_id FROM dashboard_configs WHERE dashboard_id = ?");
        $stmt->execute([$dashboard_id]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dashboard) {
            throw new Exception('Dashboard not found');
        }

        // Снимаем флаг default со всех дашбордов организации
        $stmt = $pdo->prepare("UPDATE dashboard_configs SET is_default = FALSE WHERE org_id = ?");
        $stmt->execute([$dashboard['org_id']]);

        // Устанавливаем флаг default для выбранного
        $stmt = $pdo->prepare("UPDATE dashboard_configs SET is_default = TRUE WHERE dashboard_id = ?");
        $stmt->execute([$dashboard_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Default dashboard updated successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    throw new Exception('Invalid action or method');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
