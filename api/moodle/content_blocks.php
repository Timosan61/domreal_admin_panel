<?php
/**
 * API endpoint для получения списка content blocks курса Moodle
 * GET ?action=list&courseid=13
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/moodle_database.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 13;

    try {
        $moodle_db = new MoodleDatabase();
        $conn = $moodle_db->getConnection();

        if (!$conn) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Ошибка подключения к Moodle БД'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "SELECT
            cb.id as block_id,
            cb.anchor_id,
            cb.title,
            cb.level,
            cb.page_cm_id,
            cb.block_order,
            p.name as page_name
        FROM mdl_local_ailocaquiz_content_blocks cb
        JOIN mdl_course_modules cm ON cb.page_cm_id = cm.id
        JOIN mdl_page p ON cm.instance = p.id
        WHERE cb.courseid = :courseid
        ORDER BY cb.page_cm_id, cb.block_order";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->execute();

        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($blocks as &$block) {
            $block['block_id'] = intval($block['block_id']);
            $block['level'] = intval($block['level']);
            $block['page_cm_id'] = intval($block['page_cm_id']);
            $block['block_order'] = intval($block['block_order']);
        }

        echo json_encode(['success' => true, 'data' => $blocks, 'total' => count($blocks)], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный метод или action'], JSON_UNESCAPED_UNICODE);
}
