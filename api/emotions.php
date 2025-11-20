<?php
/**
 * API для получения эмоциональных данных звонка
 *
 * Возвращает результаты гибридного анализа эмоций:
 * - BERT sentiment (менеджер/клиент)
 * - Audio features (pitch, energy, speaking rate)
 * - Детектированные сценарии (12 сценариев)
 * - Эмоциональная траектория
 *
 * GET /api/emotions.php?callid=123456789
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Проверка callid
if (!isset($_GET['callid'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing callid parameter',
        'message' => 'Please provide callid in query string'
    ]);
    exit;
}

$callid = $_GET['callid'];

try {
    // Подключение к БД
    $database = new Database();
    $pdo = $database->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database connection failed',
            'message' => 'Could not connect to database'
        ]);
        exit;
    }

    // Загружаем emotion_data из analysis_results
    $stmt = $pdo->prepare("
        SELECT
            emotion_data,
            call_date,
            client_phone,
            employee_full_name
        FROM analysis_results
        WHERE callid = :callid
        LIMIT 1
    ");

    $stmt->execute(['callid' => $callid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Call not found',
            'message' => "No analysis found for callid {$callid}"
        ]);
        exit;
    }

    // Парсим emotion_data из JSON
    $emotion_data = $result['emotion_data'] ? json_decode($result['emotion_data'], true) : null;

    if (!$emotion_data) {
        // Нет эмоциональных данных - звонок обработан до внедрения emotion analysis
        echo json_encode([
            'success' => true,
            'has_emotion_data' => false,
            'callid' => $callid,
            'call_date' => $result['call_date'],
            'client_phone' => $result['client_phone'],
            'employee_full_name' => $result['employee_full_name'],
            'message' => 'No emotion analysis available for this call'
        ]);
        exit;
    }

    // Возвращаем полные данные
    echo json_encode([
        'success' => true,
        'has_emotion_data' => true,
        'callid' => $callid,
        'call_date' => $result['call_date'],
        'client_phone' => $result['client_phone'],
        'employee_full_name' => $result['employee_full_name'],
        'emotion_data' => $emotion_data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
