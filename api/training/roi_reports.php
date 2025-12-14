<?php
/**
 * ROI Reports API for Training Recommendations
 *
 * Endpoints:
 * GET ?action=summary - Сводка по эффективности обучения
 * GET ?action=by_skill - ROI по навыкам
 * GET ?action=by_manager - ROI по менеджерам
 * GET ?action=timeline - Временная динамика улучшений
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session.php';
checkAuth();

$database = new Database();
$pdo = $database->getConnection();
$org_id = $_SESSION['org_id'] ?? 'org-legacy';
$action = $_GET['action'] ?? 'summary';

// Фильтры по датам
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    switch ($action) {
        case 'summary':
            echo json_encode(getSummary($pdo, $org_id, $date_from, $date_to));
            break;

        case 'by_skill':
            echo json_encode(getBySkill($pdo, $org_id, $date_from, $date_to));
            break;

        case 'by_manager':
            echo json_encode(getByManager($pdo, $org_id, $date_from, $date_to));
            break;

        case 'timeline':
            echo json_encode(getTimeline($pdo, $org_id, $date_from, $date_to));
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
 * Сводка по эффективности обучения
 */
function getSummary($pdo, $org_id, $date_from, $date_to) {
    // Общая статистика по рекомендациям
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_recommendations,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
            SUM(CASE WHEN moodle_webhook_sent = 1 THEN 1 ELSE 0 END) as moodle_sent,
            SUM(CASE WHEN crm_task_created = 1 THEN 1 ELSE 0 END) as crm_tasks_created
        FROM training_recommendations
        WHERE org_id = ?
          AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $recommendations = $stmt->fetch(PDO::FETCH_ASSOC);

    // Статистика из training_roi_metrics (замена training_history)
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_trainings,
            SUM(CASE WHEN score_improvement > 0 THEN 1 ELSE 0 END) as successful_trainings,
            AVG(CASE WHEN score_improvement IS NOT NULL THEN score_improvement ELSE NULL END) as avg_improvement,
            MAX(score_improvement) as max_improvement,
            MIN(CASE WHEN score_improvement > 0 THEN score_improvement ELSE NULL END) as min_improvement
        FROM training_roi_metrics
        WHERE org_id = ?
          AND status = 'completed'
          AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $history = $stmt->fetch(PDO::FETCH_ASSOC);

    // Уникальные менеджеры с рекомендациями
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT employee_full_name) as unique_managers
        FROM training_recommendations
        WHERE org_id = ?
          AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $managers = $stmt->fetch(PDO::FETCH_ASSOC);

    // Уникальные навыки с проблемами
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT skill_code) as unique_skills
        FROM training_recommendations
        WHERE org_id = ?
          AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $skills = $stmt->fetch(PDO::FETCH_ASSOC);

    // Расчет completion rate
    $total = (int)$recommendations['total_recommendations'];
    $completed = (int)$recommendations['completed'];
    $completion_rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

    // Success rate из history
    $total_trainings = (int)$history['total_trainings'];
    $successful = (int)$history['successful_trainings'];
    $success_rate = $total_trainings > 0 ? round(($successful / $total_trainings) * 100, 1) : 0;

    return [
        'success' => true,
        'data' => [
            'recommendations' => [
                'total' => $total,
                'completed' => $completed,
                'pending' => (int)$recommendations['pending'],
                'sent' => (int)$recommendations['sent'],
                'expired' => (int)$recommendations['expired'],
                'completion_rate' => $completion_rate,
                'moodle_sent' => (int)$recommendations['moodle_sent'],
                'crm_tasks_created' => (int)$recommendations['crm_tasks_created']
            ],
            'trainings' => [
                'total' => $total_trainings,
                'successful' => $successful,
                'success_rate' => $success_rate,
                'avg_improvement' => $history['avg_improvement'] ? round((float)$history['avg_improvement'], 1) : null,
                'max_improvement' => $history['max_improvement'] ? round((float)$history['max_improvement'], 1) : null,
                'min_improvement' => $history['min_improvement'] ? round((float)$history['min_improvement'], 1) : null
            ],
            'coverage' => [
                'unique_managers' => (int)$managers['unique_managers'],
                'unique_skills' => (int)$skills['unique_skills']
            ],
            'period' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ]
    ];
}

/**
 * ROI по навыкам
 */
function getBySkill($pdo, $org_id, $date_from, $date_to) {
    $stmt = $pdo->prepare("
        SELECT
            tr.skill_code,
            tr.skill_name,
            COUNT(DISTINCT tr.recommendation_id) as recommendations_count,
            COUNT(DISTINCT tr.employee_full_name) as managers_affected,
            AVG(tr.current_score) as avg_score_before,
            AVG(tr.target_score) as avg_target,
            SUM(CASE WHEN tr.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            -- Данные из training_roi_metrics
            AVG(trm.score_improvement) as avg_improvement,
            SUM(CASE WHEN trm.score_improvement > 0 THEN 1 ELSE 0 END) as successful_count
        FROM training_recommendations tr
        LEFT JOIN training_roi_metrics trm ON tr.recommendation_id = trm.recommendation_id
        WHERE tr.org_id = ?
          AND tr.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY tr.skill_code, tr.skill_name
        ORDER BY recommendations_count DESC
        LIMIT 20
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Форматирование
    $result = [];
    foreach ($skills as $skill) {
        $total = (int)$skill['recommendations_count'];
        $completed = (int)$skill['completed_count'];
        $result[] = [
            'skill_code' => $skill['skill_code'],
            'skill_name' => $skill['skill_name'] ?: $skill['skill_code'],
            'recommendations' => $total,
            'managers' => (int)$skill['managers_affected'],
            'avg_score_before' => round((float)$skill['avg_score_before'], 1),
            'avg_target' => round((float)$skill['avg_target'], 1),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'avg_improvement' => $skill['avg_improvement'] ? round((float)$skill['avg_improvement'], 1) : null,
            'success_count' => (int)$skill['successful_count']
        ];
    }

    return [
        'success' => true,
        'data' => $result
    ];
}

/**
 * ROI по менеджерам
 */
function getByManager($pdo, $org_id, $date_from, $date_to) {
    $stmt = $pdo->prepare("
        SELECT
            tr.employee_full_name,
            COUNT(DISTINCT tr.recommendation_id) as recommendations_count,
            COUNT(DISTINCT tr.skill_code) as skills_count,
            AVG(tr.current_score) as avg_score_before,
            SUM(CASE WHEN tr.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN tr.priority = 'critical' THEN 1 ELSE 0 END) as critical_count,
            SUM(CASE WHEN tr.priority = 'high' THEN 1 ELSE 0 END) as high_count,
            -- Данные из training_roi_metrics
            AVG(trm.score_improvement) as avg_improvement,
            SUM(CASE WHEN trm.score_improvement > 0 THEN 1 ELSE 0 END) as successful_count
        FROM training_recommendations tr
        LEFT JOIN training_roi_metrics trm ON tr.recommendation_id = trm.recommendation_id
        WHERE tr.org_id = ?
          AND tr.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY tr.employee_full_name
        ORDER BY recommendations_count DESC
        LIMIT 30
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Форматирование
    $result = [];
    foreach ($managers as $manager) {
        $total = (int)$manager['recommendations_count'];
        $completed = (int)$manager['completed_count'];
        $result[] = [
            'manager' => $manager['employee_full_name'],
            'recommendations' => $total,
            'skills_count' => (int)$manager['skills_count'],
            'avg_score_before' => round((float)$manager['avg_score_before'], 1),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'critical_issues' => (int)$manager['critical_count'],
            'high_issues' => (int)$manager['high_count'],
            'avg_improvement' => $manager['avg_improvement'] ? round((float)$manager['avg_improvement'], 1) : null,
            'success_count' => (int)$manager['successful_count']
        ];
    }

    return [
        'success' => true,
        'data' => $result
    ];
}

/**
 * Временная динамика улучшений
 */
function getTimeline($pdo, $org_id, $date_from, $date_to) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as recommendations_created,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN moodle_webhook_sent = 1 THEN 1 ELSE 0 END) as moodle_sent,
            SUM(CASE WHEN crm_task_created = 1 THEN 1 ELSE 0 END) as crm_created
        FROM training_recommendations
        WHERE org_id = ?
          AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Также добавим данные из training_roi_metrics
    $stmt = $pdo->prepare("
        SELECT
            DATE(after_snapshot_date) as date,
            AVG(score_improvement) as avg_improvement,
            COUNT(*) as trainings_completed
        FROM training_roi_metrics
        WHERE org_id = ?
          AND status = 'completed'
          AND after_snapshot_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(after_snapshot_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$org_id, $date_from, $date_to]);
    $improvements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Объединяем данные по датам
    $history_by_date = [];
    foreach ($improvements as $row) {
        $history_by_date[$row['date']] = [
            'avg_improvement' => round((float)$row['avg_improvement'], 1),
            'trainings_completed' => (int)$row['trainings_completed']
        ];
    }

    $result = [];
    foreach ($timeline as $row) {
        $date = $row['date'];
        $result[] = [
            'date' => $date,
            'recommendations_created' => (int)$row['recommendations_created'],
            'completed' => (int)$row['completed'],
            'moodle_sent' => (int)$row['moodle_sent'],
            'crm_created' => (int)$row['crm_created'],
            'avg_improvement' => $history_by_date[$date]['avg_improvement'] ?? null,
            'trainings_completed' => $history_by_date[$date]['trainings_completed'] ?? 0
        ];
    }

    return [
        'success' => true,
        'data' => $result
    ];
}
