<?php
/**
 * API endpoint для получения детальной информации о студенте курса Moodle
 *
 * GET ?action=get&userid=2&courseid=13
 *
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "user_id": 2,
 *     "firstname": "Admin",
 *     "lastname": "User",
 *     "email": "admin@example.com",
 *     "enrolled_at": "2025-12-10 10:00:00",
 *     "overall_progress": 50,
 *     "modules": [...]
 *   }
 * }
 *
 * @author Claude Code
 * @date 2025-12-11
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/moodle_database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'get') {
    $userid = isset($_GET['userid']) ? intval($_GET['userid']) : 0;
    $courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 13;

    if (!$userid) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Параметр userid обязателен'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $moodle_db = new MoodleDatabase();
        $conn = $moodle_db->getConnection();

        if (!$conn) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка подключения к Moodle БД'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Получаем базовую информацию о студенте
        $sql_user = "SELECT
            u.id as user_id,
            u.firstname,
            u.lastname,
            u.email,
            FROM_UNIXTIME(ue.timecreated) as enrolled_at
        FROM mdl_user u
        JOIN mdl_user_enrolments ue ON u.id = ue.userid
        JOIN mdl_enrol e ON ue.enrolid = e.id
        WHERE u.id = :userid
            AND e.courseid = :courseid
            AND u.deleted = 0
        LIMIT 1";

        $stmt = $conn->prepare($sql_user);
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Студент не найден'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Получаем детали по модулям
        $sql_modules = "SELECT
            cm.id as cm_id,
            COALESCE(p.name, q.name, f.name) as module_name,
            m.name as module_type,
            COALESCE(cmc.completionstate, 0) as completion_state,
            FROM_UNIXTIME(cmc.timemodified) as completed_at,
            -- Page scroll tracking
            pct.percentage as scroll_percentage,
            -- Quiz results
            qg.grade as quiz_grade,
            (SELECT COUNT(*) FROM mdl_quiz_attempts qa WHERE qa.quiz = q.id AND qa.userid = :userid1) as quiz_attempts,
            (SELECT FROM_UNIXTIME(MAX(qa.timefinish)) FROM mdl_quiz_attempts qa WHERE qa.quiz = q.id AND qa.userid = :userid2 AND qa.state = 'finished') as best_attempt_date
        FROM mdl_course_modules cm
        JOIN mdl_modules m ON cm.module = m.id
        LEFT JOIN mdl_page p ON p.id = cm.instance AND m.name = 'page'
        LEFT JOIN mdl_quiz q ON q.id = cm.instance AND m.name = 'quiz'
        LEFT JOIN mdl_forum f ON f.id = cm.instance AND m.name = 'forum'
        LEFT JOIN mdl_course_modules_completion cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid3
        LEFT JOIN mdl_page_content_tracking pct ON pct.pageid = p.id AND pct.userid = :userid4
        LEFT JOIN mdl_quiz_grades qg ON qg.quiz = q.id AND qg.userid = :userid5
        WHERE cm.course = :courseid
            AND cm.visible = 1
        ORDER BY cm.section, cm.id";

        $stmt = $conn->prepare($sql_modules);
        $stmt->bindParam(':userid1', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':userid2', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':userid3', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':userid4', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':userid5', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();

        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Форматирование данных
        $completed_count = 0;
        foreach ($modules as &$module) {
            $module['cm_id'] = intval($module['cm_id']);
            $module['completion_state'] = intval($module['completion_state']);

            if ($module['completion_state'] > 0) {
                $completed_count++;
            }

            // Scroll percentage для Page модулей
            if ($module['module_type'] === 'page') {
                $module['scroll_percentage'] = $module['scroll_percentage'] ? floatval($module['scroll_percentage']) : 0;
            } else {
                unset($module['scroll_percentage']);
            }

            // Quiz данные
            if ($module['module_type'] === 'quiz') {
                $module['quiz_grade'] = $module['quiz_grade'] ? floatval($module['quiz_grade']) : null;
                $module['quiz_attempts'] = intval($module['quiz_attempts']);
            } else {
                unset($module['quiz_grade']);
                unset($module['quiz_attempts']);
                unset($module['best_attempt_date']);
            }
        }

        // Подсчет общего прогресса
        $total_modules = count($modules);
        $overall_progress = $total_modules > 0 ? round(($completed_count / $total_modules) * 100, 1) : 0;

        $user['user_id'] = intval($user['user_id']);
        $user['overall_progress'] = $overall_progress;
        $user['modules'] = $modules;

        echo json_encode([
            'success' => true,
            'data' => $user
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
