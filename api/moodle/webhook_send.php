<?php
/**
 * API endpoint для отправки webhook рекомендаций студентам
 *
 * POST ?action=send
 *
 * Request Body:
 * {
 *   "user_id": 2,
 *   "anchor_id": "cm84-table-of-contents",
 *   "priority": "high",
 *   "reason": "Низкий балл в тесте"
 * }
 *
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "recommendation_id": 123,
 *     "deep_link": "https://academy.ailoca.ru/mod/page/view.php?id=84#cm84-table-of-contents",
 *     "webhook_status": "sent",
 *     "moodle_notification_id": 456
 *   }
 * }
 *
 * @author Claude Code
 * @date 2025-12-11
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/moodle_database.php';
require_once __DIR__ . '/../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'send') {
    // Получаем JSON данные
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
    $anchor_id = $data['anchor_id'] ?? '';
    $priority = $data['priority'] ?? 'medium';
    $reason = $data['reason'] ?? '';
    $courseid = isset($data['courseid']) ? intval($data['courseid']) : 13;

    // Валидация
    if (!$user_id || !$anchor_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Параметры user_id и anchor_id обязательны'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($priority, ['high', 'medium', 'low'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Приоритет должен быть: high, medium или low'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Подключаемся к Moodle БД
        $moodle_db = new MoodleDatabase();
        $moodle_conn = $moodle_db->getConnection();

        if (!$moodle_conn) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка подключения к Moodle БД'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 1. Проверяем существование anchor_id и получаем данные content block
        $sql_block = "SELECT
            cb.id,
            cb.anchor_id,
            cb.title,
            cb.page_cm_id,
            p.name as page_name
        FROM mdl_local_ailocaquiz_content_blocks cb
        JOIN mdl_course_modules cm ON cb.page_cm_id = cm.id
        JOIN mdl_page p ON cm.instance = p.id
        WHERE cb.anchor_id = :anchor_id
            AND cb.courseid = :courseid
        LIMIT 1";

        $stmt = $moodle_conn->prepare($sql_block);
        $stmt->bindParam(':anchor_id', $anchor_id, PDO::PARAM_STR);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();

        $block = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$block) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Content block с anchor_id "' . $anchor_id . '" не найден'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. Проверяем существование пользователя
        $sql_user = "SELECT id, firstname, lastname, email
                     FROM mdl_user
                     WHERE id = :user_id AND deleted = 0
                     LIMIT 1";

        $stmt = $moodle_conn->prepare($sql_user);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Пользователь с ID ' . $user_id . ' не найден'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. Генерируем deep link
        $deep_link = 'https://academy.ailoca.ru/mod/page/view.php?id=' . $block['page_cm_id'] . '#' . $anchor_id;

        // 4. Отправляем webhook на Moodle
        $webhook_url = 'https://academy.ailoca.ru/local/ailocaquiz/api/webhook/recommend.php';
        $webhook_token = 'JnUOD8vGkOrEi902cjgWAhFwywOvIoaef9qDauGV7+8=';

        $webhook_payload = [
            'user_id' => $user_id,
            'anchor_id' => $anchor_id,
            'section_title' => $block['title'],
            'priority' => $priority,
            'reason' => $reason
        ];

        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $webhook_token
        ]);

        $webhook_response = curl_exec($ch);
        $webhook_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $webhook_error = curl_error($ch);
        curl_close($ch);

        // Парсим ответ Moodle webhook
        $moodle_notification_id = null;
        $webhook_status = 'failed';
        $error_message = null;

        if ($webhook_http_code === 200) {
            $webhook_result = json_decode($webhook_response, true);
            if ($webhook_result && isset($webhook_result['success']) && $webhook_result['success']) {
                $webhook_status = 'sent';
                $moodle_notification_id = $webhook_result['notification_id'] ?? null;
            } else {
                $error_message = $webhook_result['error'] ?? 'Unknown error from Moodle webhook';
            }
        } else {
            $error_message = 'HTTP ' . $webhook_http_code . ': ' . ($webhook_error ?: $webhook_response);
        }

        // 5. Сохраняем запись в calls_db.moodle_webhook_recommendations
        $database = new Database();
        $calls_conn = $database->getConnection();

        if (!$calls_conn) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка подключения к БД для сохранения истории'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql_insert = "INSERT INTO moodle_webhook_recommendations
            (courseid, user_id, anchor_id, priority, reason, status, deep_link, moodle_notification_id, error_message, sent_at)
            VALUES
            (:courseid, :user_id, :anchor_id, :priority, :reason, :status, :deep_link, :moodle_notification_id, :error_message, NOW())";

        $stmt = $calls_conn->prepare($sql_insert);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':anchor_id', $anchor_id, PDO::PARAM_STR);
        $stmt->bindParam(':priority', $priority, PDO::PARAM_STR);
        $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $stmt->bindParam(':status', $webhook_status, PDO::PARAM_STR);
        $stmt->bindParam(':deep_link', $deep_link, PDO::PARAM_STR);
        $stmt->bindParam(':moodle_notification_id', $moodle_notification_id, PDO::PARAM_INT);
        $stmt->bindParam(':error_message', $error_message, PDO::PARAM_STR);
        $stmt->execute();

        $recommendation_id = $calls_conn->lastInsertId();

        // 6. Возвращаем результат
        echo json_encode([
            'success' => true,
            'data' => [
                'recommendation_id' => intval($recommendation_id),
                'user_id' => $user_id,
                'user_name' => $user['firstname'] . ' ' . $user['lastname'],
                'anchor_id' => $anchor_id,
                'block_title' => $block['title'],
                'page_name' => $block['page_name'],
                'deep_link' => $deep_link,
                'priority' => $priority,
                'reason' => $reason,
                'webhook_status' => $webhook_status,
                'moodle_notification_id' => $moodle_notification_id,
                'error_message' => $error_message,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка БД: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Неверный метод или action'
    ], JSON_UNESCAPED_UNICODE);
}
