<?php
/**
 * API endpoint для получения статистики webhook рекомендаций
 *
 * GET ?action=summary&courseid=13
 *
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "total_sent": 123,
 *     "total_viewed": 45,
 *     "total_failed": 2,
 *     "view_rate": 36.6,
 *     "by_priority": {
 *       "high": 30,
 *       "medium": 60,
 *       "low": 33
 *     },
 *     "by_status": {
 *       "sent": 121,
 *       "viewed": 45,
 *       "failed": 2
 *     },
 *     "recent_activity": [
 *       {
 *         "date": "2025-12-10",
 *         "sent": 15,
 *         "viewed": 6
 *       }
 *     ]
 *   }
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

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'summary') {
    $courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 13;
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30; // Период для recent_activity

    try {
        $database = new Database();
        $conn = $database->getConnection();

        if (!$conn) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка подключения к БД'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 1. Общая статистика
        $sql_total = "SELECT
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'viewed' THEN 1 ELSE 0 END) as total_viewed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid";

        $stmt = $conn->prepare($sql_total);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_sent = intval($totals['total_sent']);
        $total_viewed = intval($totals['total_viewed']);
        $total_failed = intval($totals['total_failed']);
        $view_rate = $total_sent > 0 ? round(($total_viewed / $total_sent) * 100, 1) : 0;

        // 2. Статистика по приоритетам
        $sql_priority = "SELECT
            priority,
            COUNT(*) as count
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid
        GROUP BY priority";

        $stmt = $conn->prepare($sql_priority);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();
        $priority_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $by_priority = [
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        foreach ($priority_stats as $stat) {
            $by_priority[$stat['priority']] = intval($stat['count']);
        }

        // 3. Статистика по статусам
        $sql_status = "SELECT
            status,
            COUNT(*) as count
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid
        GROUP BY status";

        $stmt = $conn->prepare($sql_status);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();
        $status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $by_status = [];
        foreach ($status_stats as $stat) {
            $by_status[$stat['status']] = intval($stat['count']);
        }

        // 4. Активность за последние N дней
        $sql_activity = "SELECT
            DATE(sent_at) as date,
            COUNT(*) as sent,
            SUM(CASE WHEN status = 'viewed' THEN 1 ELSE 0 END) as viewed
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid
            AND sent_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        GROUP BY DATE(sent_at)
        ORDER BY date DESC";

        $stmt = $conn->prepare($sql_activity);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Форматируем активность
        $recent_activity = [];
        foreach ($activity as $day) {
            $recent_activity[] = [
                'date' => $day['date'],
                'sent' => intval($day['sent']),
                'viewed' => intval($day['viewed']),
                'view_rate' => intval($day['sent']) > 0 ? round((intval($day['viewed']) / intval($day['sent'])) * 100, 1) : 0
            ];
        }

        // 5. Топ студентов (получивших больше всего рекомендаций)
        $sql_top_users = "SELECT
            user_id,
            COUNT(*) as recommendations_received,
            SUM(CASE WHEN status = 'viewed' THEN 1 ELSE 0 END) as viewed
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid
        GROUP BY user_id
        ORDER BY recommendations_received DESC
        LIMIT 10";

        $stmt = $conn->prepare($sql_top_users);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();
        $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Форматируем top users
        $top_recipients = [];
        foreach ($top_users as $user) {
            $top_recipients[] = [
                'user_id' => intval($user['user_id']),
                'recommendations_received' => intval($user['recommendations_received']),
                'viewed' => intval($user['viewed']),
                'view_rate' => intval($user['recommendations_received']) > 0
                    ? round((intval($user['viewed']) / intval($user['recommendations_received'])) * 100, 1)
                    : 0
            ];
        }

        // 6. Топ рекомендуемых блоков
        $sql_top_blocks = "SELECT
            anchor_id,
            COUNT(*) as times_recommended
        FROM moodle_webhook_recommendations
        WHERE courseid = :courseid
        GROUP BY anchor_id
        ORDER BY times_recommended DESC
        LIMIT 10";

        $stmt = $conn->prepare($sql_top_blocks);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();
        $top_blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Форматируем top blocks
        $top_recommended_blocks = [];
        foreach ($top_blocks as $block) {
            $top_recommended_blocks[] = [
                'anchor_id' => $block['anchor_id'],
                'times_recommended' => intval($block['times_recommended'])
            ];
        }

        // Возвращаем все статистики
        echo json_encode([
            'success' => true,
            'data' => [
                'total_sent' => $total_sent,
                'total_viewed' => $total_viewed,
                'total_failed' => $total_failed,
                'view_rate' => $view_rate,
                'by_priority' => $by_priority,
                'by_status' => $by_status,
                'recent_activity' => $recent_activity,
                'top_recipients' => $top_recipients,
                'top_recommended_blocks' => $top_recommended_blocks,
                'period_days' => $days
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
