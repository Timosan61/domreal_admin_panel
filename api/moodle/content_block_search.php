<?php
/**
 * API endpoint для поиска content blocks по названию или anchor_id
 * GET ?action=search&query=table&courseid=13
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'search') {
    $query = $_GET['query'] ?? '';
    $courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 13;

    if (empty($query)) {
        echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $moodle_db = new MoodleDatabase();
        $conn = $moodle_db->getConnection();

        if (!$conn) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Ошибка подключения к Moodle БД'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $search = '%' . $query . '%';
        $sql = "SELECT
            cb.anchor_id,
            cb.title,
            p.name as page_name,
            cb.page_cm_id
        FROM mdl_local_ailocaquiz_content_blocks cb
        JOIN mdl_course_modules cm ON cb.page_cm_id = cm.id
        JOIN mdl_page p ON cm.instance = p.id
        WHERE cb.courseid = :courseid
            AND (cb.title LIKE :search1 OR cb.anchor_id LIKE :search2)
        ORDER BY cb.title
        LIMIT 20";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':courseid', $courseid, PDO::PARAM_INT);
        $stmt->bindParam(':search1', $search, PDO::PARAM_STR);
        $stmt->bindParam(':search2', $search, PDO::PARAM_STR);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            $result['page_cm_id'] = intval($result['page_cm_id']);
        }

        echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный метод или action'], JSON_UNESCAPED_UNICODE);
}
