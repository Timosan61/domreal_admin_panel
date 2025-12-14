<?php
/**
 * API endpoint для получения списка студентов курса Moodle с прогрессом
 *
 * GET ?action=list&courseid=13
 *
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "user_id": 2,
 *       "firstname": "Admin",
 *       "lastname": "User",
 *       "email": "admin@example.com",
 *       "enrolled_at": "2025-12-10 10:00:00",
 *       "overall_progress": 50,
 *       "modules_completed": 2,
 *       "modules_total": 4
 *     }
 *   ]
 * }
 *
 * @author Claude Code
 * @date 2025-12-11
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/moodle_database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    $courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 13;

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

        // SQL запрос для получения списка студентов с прогрессом и резюме
        $sql = "SELECT
            u.id as user_id,
            u.firstname,
            u.lastname,
            u.email,
            FROM_UNIXTIME(ue.timecreated) as enrolled_at,
            -- Общий прогресс
            COUNT(DISTINCT CASE WHEN cmc.completionstate > 0 THEN cmc.coursemoduleid END) as modules_completed,
            COUNT(DISTINCT cm.id) as modules_total,
            ROUND(100.0 * COUNT(DISTINCT CASE WHEN cmc.completionstate > 0 THEN cmc.coursemoduleid END) / NULLIF(COUNT(DISTINCT cm.id), 0), 1) as overall_progress,
            -- Резюме из таблицы регистраций
            pr.resume_filename,
            pr.resume_itemid
        FROM mdl_user u
        JOIN mdl_user_enrolments ue ON u.id = ue.userid
        JOIN mdl_enrol e ON ue.enrolid = e.id
        LEFT JOIN mdl_course_modules cm ON cm.course = e.courseid AND cm.visible = 1
        LEFT JOIN mdl_course_modules_completion cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
        LEFT JOIN mdl_local_pubreg_log pr ON pr.user_id = u.id
        WHERE e.courseid = :courseid
            AND u.deleted = 0
        GROUP BY u.id, u.firstname, u.lastname, u.email, ue.timecreated, pr.resume_filename, pr.resume_itemid
        ORDER BY u.lastname, u.firstname";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();

        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Форматирование данных
        foreach ($students as &$student) {
            $student['user_id'] = intval($student['user_id']);
            $student['modules_completed'] = intval($student['modules_completed']);
            $student['modules_total'] = intval($student['modules_total']);
            $student['overall_progress'] = floatval($student['overall_progress']);

            // Форматирование данных резюме
            if (!empty($student['resume_filename'])) {
                $student['resume'] = [
                    'filename' => $student['resume_filename'],
                    'download_url' => '/api/moodle/resume_download.php?user_id=' . $student['user_id']
                ];
            } else {
                $student['resume'] = null;
            }
            unset($student['resume_filename']);
            unset($student['resume_itemid']);
        }

        echo json_encode([
            'success' => true,
            'data' => $students,
            'total' => count($students)
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
