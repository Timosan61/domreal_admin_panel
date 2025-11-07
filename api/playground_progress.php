<?php
/**
 * API для отслеживания прогресса анализа
 * GET /api/playground_progress.php?task_id=xxx&date=2025-10-22&total=20
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once '../auth/session.php';
checkAuth(true, true);

include_once '../config/database.php';

$task_id = $_GET['task_id'] ?? '';
$date = $_GET['date'] ?? '';
$total_expected = (int)($_GET['total'] ?? 20);

if (empty($date)) {
    http_response_code(400);
    echo json_encode(["error" => "date required"]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Подсчитываем сколько звонков уже проанализировано
    $query = "SELECT COUNT(DISTINCT callid) as analyzed_count
              FROM playground_analyses
              WHERE callid IN (
                  SELECT callid FROM calls_raw
                  WHERE DATE(started_at_utc) = :date
                  LIMIT :total
              )
              AND analyzed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':total', $total_expected, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $analyzed_count = (int)$row['analyzed_count'];

    // Вычисляем прогресс
    $progress = $total_expected > 0 ? ($analyzed_count / $total_expected) * 100 : 0;
    $is_complete = $analyzed_count >= $total_expected;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'task_id' => $task_id,
        'progress' => round($progress, 1),
        'analyzed_count' => $analyzed_count,
        'total' => $total_expected,
        'is_complete' => $is_complete
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>