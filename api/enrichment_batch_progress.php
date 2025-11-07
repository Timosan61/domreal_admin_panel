<?php
/**
 * API для real-time прогресса batch через SSE
 * GET /api/enrichment_batch_progress.php?batch_id=123
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx buffering off

session_start();
require_once '../auth/session.php';
checkAuth($require_admin = true, $is_api = true);

include_once '../config/database.php';

$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

if (!$batch_id) {
    echo "data: " . json_encode(["error" => "batch_id required"]) . "\n\n";
    flush();
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Отправляем обновления каждые 2 секунды
set_time_limit(300); // 5 минут max
$max_iterations = 150; // 150 * 2 сек = 5 минут
$iteration = 0;

while ($iteration < $max_iterations) {
    $query = "SELECT
        total_records,
        processed_records,
        completed_records,
        error_records,
        pending_records,
        inn_found_count,
        status
    FROM enrichment_batches
    WHERE id = :batch_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':batch_id', $batch_id);
    $stmt->execute();
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        echo "data: " . json_encode(["error" => "batch not found"]) . "\n\n";
        flush();
        break;
    }

    $total = intval($batch['total_records']);
    $processed = intval($batch['processed_records']);
    $percent = $total > 0 ? round(($processed / $total) * 100) : 0;

    $data = [
        'total' => $total,
        'processed' => $processed,
        'completed' => intval($batch['completed_records']),
        'error' => intval($batch['error_records']),
        'pending' => intval($batch['pending_records']),
        'inn_found' => intval($batch['inn_found_count']),
        'percent' => $percent,
        'status' => $batch['status']
    ];

    echo "data: " . json_encode($data) . "\n\n";
    flush();

    // Если завершено - закрываем соединение
    if ($batch['status'] === 'completed' || $processed >= $total) {
        break;
    }

    sleep(2);
    $iteration++;
}
?>