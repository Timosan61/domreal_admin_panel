<?php
/**
 * API для получения результатов Playground анализа
 * GET /api/playground_results.php?call_ids=xxx,yyy&models=gigachat,openai
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// TEST VERSION - NO AUTH
session_start();
$_SESSION['username'] = 'test_admin';
$_SESSION['role'] = 'admin';

include_once '../config/database.php';

$call_ids_str = $_GET['call_ids'] ?? '';
$models_str = $_GET['models'] ?? 'gigachat,openai';

if (empty($call_ids_str)) {
    http_response_code(400);
    echo json_encode(["error" => "call_ids required"]);
    exit();
}

$call_ids = explode(',', $call_ids_str);
$models = explode(',', $models_str);

$database = new Database();
$db = $database->getConnection();

try {
    // Получаем результаты playground анализа
    $placeholders = str_repeat('?,', count($call_ids) - 1) . '?';

    $query = "SELECT
        callid,
        model_name,
        call_summary,
        call_result,
        is_successful,
        script_compliance_score,
        analyzed_at,
        analysis_duration_sec
    FROM playground_analyses
    WHERE callid IN ($placeholders)
    ORDER BY callid, model_name";

    $stmt = $db->prepare($query);
    $stmt->execute($call_ids);

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $callid = $row['callid'];
        $model = $row['model_name'];

        if (!isset($results[$callid])) {
            $results[$callid] = [];
        }

        $results[$callid][$model] = [
            'summary' => $row['call_summary'],
            'result' => $row['call_result'],
            'is_successful' => (bool)$row['is_successful'],
            'script_score' => (int)$row['script_compliance_score'],
            'analyzed_at' => $row['analyzed_at'],
            'duration' => (float)$row['analysis_duration_sec']
        ];
    }

    // Получаем агрегации
    $query_agg = "SELECT
        client_phone,
        model_name,
        aggregate_summary,
        client_overall_status,
        calls_count,
        successful_calls_count
    FROM playground_aggregations
    WHERE model_name IN ('" . implode("','", $models) . "')";

    $stmt_agg = $db->prepare($query_agg);
    $stmt_agg->execute();

    $aggregations = [];
    while ($row = $stmt_agg->fetch(PDO::FETCH_ASSOC)) {
        $phone = $row['client_phone'];
        $model = $row['model_name'];

        if (!isset($aggregations[$phone])) {
            $aggregations[$phone] = [];
        }

        $aggregations[$phone][$model] = [
            'summary' => $row['aggregate_summary'],
            'status' => $row['client_overall_status'],
            'calls_count' => (int)$row['calls_count'],
            'successful_count' => (int)$row['successful_calls_count']
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'results' => $results,
        'aggregations' => $aggregations
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>