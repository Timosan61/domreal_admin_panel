<?php
/**
 * API endpoint для получения истории отправленных webhook рекомендаций
 *
 * GET ?action=list&courseid=13&limit=50&offset=0
 *
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "recommendation_id": 123,
 *       "user_id": 2,
 *       "user_name": "Admin User",
 *       "anchor_id": "cm84-table-of-contents",
 *       "block_title": "Table of Contents",
 *       "priority": "high",
 *       "reason": "Низкий балл в тесте",
 *       "status": "sent",
 *       "deep_link": "https://...",
 *       "sent_at": "2025-12-10 15:00:00",
 *       "viewed_at": null,
 *       "error_message": null
 *     }
 *   ],
 *   "total": 123,
 *   "limit": 50,
 *   "offset": 0
 * }
 *
 * @author Claude Code
 * @date 2025-12-11
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/moodle_database.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 13;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Ограничения
    if ($limit > 200) {
        $limit = 200;
    }
    if ($limit < 1) {
        $limit = 50;
    }
    if ($offset < 0) {
        $offset = 0;
    }

    try {
        // Подключаемся к calls_db (история webhooks)
        $database = new Database();
        $calls_conn = $database->getConnection();

        if (!$calls_conn) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка подключения к БД'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Подключаемся к Moodle БД (для имен пользователей и блоков)
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

        // Получаем общее количество записей
        $sql_count = "SELECT COUNT(*) as total
                      FROM moodle_webhook_recommendations
                      WHERE courseid = :courseid";

        $stmt = $calls_conn->prepare($sql_count);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = intval($count_result['total']);

        // Получаем записи истории
        $sql_history = "SELECT
            id as recommendation_id,
            user_id,
            anchor_id,
            priority,
            reason,
            status,
            deep_link,
            moodle_notification_id,
            sent_at,
            viewed_at,
            error_message
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid
        ORDER BY sent_at DESC
        LIMIT :limit OFFSET :offset";

        $stmt = $calls_conn->prepare($sql_history);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Обогащаем данные: добавляем имена пользователей и названия блоков
        foreach ($history as &$record) {
            $record['recommendation_id'] = intval($record['recommendation_id']);
            $record['user_id'] = intval($record['user_id']);
            $record['moodle_notification_id'] = $record['moodle_notification_id'] ? intval($record['moodle_notification_id']) : null;

            // Получаем имя пользователя
            $sql_user = "SELECT CONCAT(firstname, ' ', lastname) as user_name, email
                         FROM mdl_user
                         WHERE id = :user_id
                         LIMIT 1";

            $stmt = $moodle_conn->prepare($sql_user);
            $stmt->bindParam(':user_id', $record['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $record['user_name'] = $user ? $user['user_name'] : 'Unknown User';
            $record['user_email'] = $user ? $user['email'] : null;

            // Получаем название блока
            $sql_block = "SELECT title, page_cm_id
                          FROM mdl_local_ailocaquiz_content_blocks
                          WHERE anchor_id = :anchor_id AND courseid = :courseid
                          LIMIT 1";

            $stmt = $moodle_conn->prepare($sql_block);
            $stmt->bindParam(':anchor_id', $record['anchor_id'], PDO::PARAM_STR);
            $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
            $stmt->execute();
            $block = $stmt->fetch(PDO::FETCH_ASSOC);

            $record['block_title'] = $block ? $block['title'] : 'Unknown Block';
            $record['page_cm_id'] = $block ? intval($block['page_cm_id']) : null;
        }

        echo json_encode([
            'success' => true,
            'data' => $history,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
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
