<?php
/**
 * API для получения детальной информации о звонке
 * GET /api/call_details.php?callid=xxx
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

// Получаем callid
$callid = isset($_GET['callid']) ? $_GET['callid'] : '';

if (empty($callid)) {
    http_response_code(400);
    echo json_encode(["error" => "Parameter 'callid' is required"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем полную информацию о звонке
$query = "SELECT
    cr.*,
    t.text as transcript_text,
    t.diarization_json,
    t.audio_duration_sec,
    t.confidence_avg,
    t.processing_time_ms,
    ar.call_type,
    ar.summary_text,
    ar.score_overall,
    ar.conversion_probability,
    ar.emotion_tone,
    ar.metrics_json,
    ar.coaching_text,
    ar.questions_text,
    ar.has_objections,
    ar.raw_response as llm_analysis,
    aj.local_path as audio_path,
    aj.status as audio_status,
    aj.file_size_bytes,
    aj.file_format
FROM calls_raw cr
LEFT JOIN transcripts t ON cr.callid = t.callid
LEFT JOIN analysis_results ar ON cr.callid = ar.callid
LEFT JOIN audio_jobs aj ON cr.callid = aj.callid
WHERE cr.callid = :callid
LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':callid', $callid);
$stmt->execute();

$call = $stmt->fetch();

if (!$call) {
    http_response_code(404);
    echo json_encode(["error" => "Call not found"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Парсим JSON поля
if (!empty($call['diarization_json'])) {
    $call['diarization'] = json_decode($call['diarization_json'], true);
    unset($call['diarization_json']);
}

if (!empty($call['payload_json'])) {
    $call['payload'] = json_decode($call['payload_json'], true);
    unset($call['payload_json']);
}

// Парсим metrics_json и формируем чеклист
$checklist = [];
$metrics = [];

if (!empty($call['metrics_json'])) {
    $metrics = json_decode($call['metrics_json'], true);

    // Формируем чеклист на основе metrics_json
    if ($call['call_type'] === 'first_call' && isset($metrics['script_checks'])) {
        $checks = $metrics['script_checks'];

        $checklist = [
            [
                'id' => 'location',
                'label' => 'Местоположение клиента выяснено',
                'checked' => isset($checks['location']) ? boolval($checks['location']) : false,
                'description' => 'Менеджер уточнил, где именно клиент ищет недвижимость'
            ],
            [
                'id' => 'payment',
                'label' => 'Форма оплаты выяснена',
                'checked' => isset($checks['payment']) ? boolval($checks['payment']) : false,
                'description' => 'Уточнена форма оплаты (наличные, ипотека, рассрочка)'
            ],
            [
                'id' => 'goal',
                'label' => 'Цель покупки выяснена',
                'checked' => isset($checks['goal']) ? boolval($checks['goal']) : false,
                'description' => 'Выяснена цель покупки (инвестиция, для себя, для сдачи)'
            ],
            [
                'id' => 'is_local',
                'label' => 'Местный ли клиент',
                'checked' => isset($checks['is_local']) ? boolval($checks['is_local']) : false,
                'description' => 'Определено, находится ли клиент в городе или регионе'
            ],
            [
                'id' => 'budget',
                'label' => 'Бюджет выяснен',
                'checked' => isset($checks['budget']) ? boolval($checks['budget']) : false,
                'description' => 'Уточнен бюджет клиента на покупку'
            ]
        ];
    }
}

$call['checklist'] = $checklist;
$call['metrics'] = $metrics;

// Добавляем дополнительные поля для обратной совместимости
$call['script_compliance_score'] = $call['score_overall'] ? ($call['score_overall'] / 10) : null;
$call['is_successful'] = $call['conversion_probability'] > 0.5 ? true : false;
$call['call_result'] = isset($metrics['call_result']) ? $metrics['call_result'] : 'unknown';
$call['success_reason'] = isset($metrics['success_reason']) ? $metrics['success_reason'] : null;

// Формируем ответ
$response = [
    "success" => true,
    "data" => $call
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
