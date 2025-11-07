<?php
/**
 * API для получения звонков за дату для Playground
 * GET /api/playground_calls.php?date=2025-10-22&limit=20
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// TEST VERSION - NO AUTH
session_start();
$_SESSION['username'] = 'test_admin';
$_SESSION['role'] = 'admin';

include_once '../config/database.php';

// Параметры
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;

// Валидация даты
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid date format. Use YYYY-MM-DD"]);
    exit();
}

// Подключение к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

try {
    // Получаем звонки за дату с транскрипциями
    $query = "SELECT
        cr.callid,
        cr.client_phone,
        cr.employee_name,
        cr.department,
        cr.direction,
        cr.duration_sec,
        cr.started_at_utc,
        t.audio_duration_sec,

        -- Production результаты
        ar.call_type,
        ar.summary_text as production_summary,
        ar.call_result as production_result,
        ar.is_successful as production_is_successful,
        ar.script_compliance_score as production_script_score

    FROM calls_raw cr
    INNER JOIN transcripts t ON cr.callid = t.callid
    LEFT JOIN analysis_results ar ON cr.callid = ar.callid
    WHERE DATE(cr.started_at_utc) = :date
        AND t.text IS NOT NULL
        AND t.text != ''
        AND cr.duration_sec >= 10
    ORDER BY cr.started_at_utc ASC
    LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $calls = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $calls[] = [
            'callid' => $row['callid'],
            'client_phone' => $row['client_phone'],
            'employee_name' => $row['employee_name'],
            'department' => $row['department'],
            'direction' => $row['direction'],
            'duration_sec' => (int)$row['duration_sec'],
            'started_at_utc' => $row['started_at_utc'],
            'audio_duration_sec' => (float)$row['audio_duration_sec'],

            // Production результаты
            'production' => [
                'call_type' => $row['call_type'],
                'summary' => $row['production_summary'],
                'result' => $row['production_result'],
                'is_successful' => (bool)$row['production_is_successful'],
                'script_score' => (int)$row['production_script_score']
            ]
        ];
    }

    // Успешный ответ
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'date' => $date,
        'limit' => $limit,
        'count' => count($calls),
        'calls' => $calls
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>