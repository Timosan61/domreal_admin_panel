<?php
/**
 * Training Recommendations API
 *
 * Endpoints:
 * GET ?action=stats - Статистика по рекомендациям
 * GET ?action=managers - Список менеджеров с рекомендациями
 * GET ?action=skills - Список навыков
 * GET ?action=list - Список рекомендаций (с фильтрами)
 * POST ?action=complete - Отметить рекомендацию выполненной
 * POST ?action=resend - Повторно отправить webhook
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/session.php';
checkAuth(false, true);

$database = new Database();
$pdo = $database->getConnection();
$org_id = $_SESSION['org_id'] ?? 'org-legacy';
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'stats':
            echo json_encode(getStats($pdo, $org_id));
            break;

        case 'managers':
            echo json_encode(getManagers($pdo, $org_id));
            break;

        case 'skills':
            echo json_encode(getSkills($pdo, $org_id));
            break;

        case 'list':
            $filters = [
                'manager' => $_GET['manager'] ?? null,
                'skill' => $_GET['skill'] ?? null,
                'priority' => $_GET['priority'] ?? null,
                'status' => $_GET['status'] ?? null
            ];
            echo json_encode(getList($pdo, $org_id, $filters));
            break;

        case 'complete':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(markComplete($pdo, $org_id, $data));
            break;

        case 'resend':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(resendWebhook($pdo, $org_id, $data));
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Статистика по рекомендациям
 */
function getStats($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
            COUNT(DISTINCT employee_full_name) as unique_managers,
            COUNT(DISTINCT skill_code) as unique_skills
        FROM training_recommendations
        WHERE org_id = ?
    ");
    $stmt->execute([$org_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data' => [
            'total' => (int)$stats['total'],
            'pending' => (int)$stats['pending'],
            'sent' => (int)$stats['sent'],
            'completed' => (int)$stats['completed'],
            'critical' => (int)$stats['critical'],
            'high' => (int)$stats['high'],
            'unique_managers' => (int)$stats['unique_managers'],
            'unique_skills' => (int)$stats['unique_skills']
        ]
    ];
}

/**
 * Список менеджеров с рекомендациями
 */
function getManagers($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT employee_full_name, COUNT(*) as count
        FROM training_recommendations
        WHERE org_id = ?
        GROUP BY employee_full_name
        ORDER BY count DESC
    ");
    $stmt->execute([$org_id]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data' => $managers
    ];
}

/**
 * Список навыков
 */
function getSkills($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT skill_code, skill_name, COUNT(*) as count
        FROM training_recommendations
        WHERE org_id = ?
        GROUP BY skill_code, skill_name
        ORDER BY count DESC
    ");
    $stmt->execute([$org_id]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format skill names
    foreach ($skills as &$skill) {
        if (empty($skill['skill_name'])) {
            $skill['skill_name'] = $skill['skill_code'];
        }
    }

    return [
        'success' => true,
        'data' => $skills
    ];
}

/**
 * Список рекомендаций с фильтрами
 */
function getList($pdo, $org_id, $filters) {
    $where = ["org_id = ?"];
    $params = [$org_id];

    if (!empty($filters['manager'])) {
        $where[] = "employee_full_name = ?";
        $params[] = $filters['manager'];
    }

    if (!empty($filters['skill'])) {
        $where[] = "skill_code = ?";
        $params[] = $filters['skill'];
    }

    if (!empty($filters['priority'])) {
        $where[] = "priority = ?";
        $params[] = $filters['priority'];
    }

    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            recommendation_id,
            employee_full_name,
            skill_code,
            skill_name,
            current_score,
            target_score,
            priority,
            status,
            trigger_type,
            trigger_details,
            moodle_webhook_sent,
            moodle_webhook_sent_at,
            crm_task_created,
            crm_task_id,
            created_at,
            completed_at
        FROM training_recommendations
        WHERE $whereClause
        ORDER BY
            FIELD(priority, 'critical', 'high', 'medium', 'low'),
            created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data
    foreach ($recommendations as &$rec) {
        $rec['current_score'] = round((float)$rec['current_score'], 1);
        $rec['target_score'] = round((float)$rec['target_score'], 1);
        if (empty($rec['skill_name'])) {
            $rec['skill_name'] = $rec['skill_code'];
        }
        if ($rec['trigger_details']) {
            $rec['trigger_details'] = json_decode($rec['trigger_details'], true);
        }
    }

    return [
        'success' => true,
        'data' => $recommendations
    ];
}

/**
 * Отметить рекомендацию выполненной
 */
function markComplete($pdo, $org_id, $data) {
    if (empty($data['recommendation_id'])) {
        return ['success' => false, 'error' => 'recommendation_id required'];
    }

    $stmt = $pdo->prepare("
        UPDATE training_recommendations
        SET status = 'completed',
            completed_at = NOW(),
            updated_at = NOW()
        WHERE recommendation_id = ?
          AND org_id = ?
    ");
    $stmt->execute([$data['recommendation_id'], $org_id]);

    return [
        'success' => true,
        'message' => 'Recommendation marked as completed'
    ];
}

/**
 * Повторно отправить webhook (заглушка)
 */
function resendWebhook($pdo, $org_id, $data) {
    if (empty($data['recommendation_id'])) {
        return ['success' => false, 'error' => 'recommendation_id required'];
    }

    // TODO: Implement actual webhook resend logic
    return [
        'success' => true,
        'message' => 'Webhook resend queued'
    ];
}
