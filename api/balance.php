<?php
/**
 * API для получения баланса STT и ИИ (в минутах и секундах)
 *
 * Endpoints:
 * - GET ?project_id=X - Получить баланс STT и ИИ для проекта
 *
 * Возвращаемые данные:
 * - stt_balance: {minutes, seconds, total_seconds} - Баланс STT транскрибации
 * - ai_balance: {minutes, seconds, total_seconds} - Баланс ИИ анализа
 * - total_calls: Общее количество звонков в проекте
 * - transcribed_calls: Количество транскрибированных звонков
 * - analyzed_calls: Количество проанализированных звонков
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Отключаем кеширование для получения актуальных данных
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../auth/session.php';
checkAuth(false, true); // Проверка авторизации для API endpoint

include_once '../config/database.php';

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * GET - Получить баланс STT и ИИ для проекта
 */
if ($method === 'GET') {
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

    if (!$project_id) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Не указан project_id"
        ]);
        exit();
    }

    // Проверяем, существует ли проект
    $check_query = "SELECT id, name FROM projects WHERE id = :project_id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $project = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Проект не найден"
        ]);
        exit();
    }

    try {
        // Получаем общую статистику по звонкам проекта
        $stats_query = "SELECT
            COUNT(*) as total_calls,
            SUM(duration_sec) as total_duration_sec
        FROM calls_raw
        WHERE project_id = :project_id";

        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем статистику по транскрипциям (STT баланс)
        $stt_query = "SELECT
            COUNT(DISTINCT t.callid) as transcribed_calls,
            SUM(t.audio_duration_sec) as transcribed_duration_sec
        FROM transcripts t
        JOIN calls_raw cr ON t.callid = cr.callid
        WHERE cr.project_id = :project_id";

        $stt_stmt = $db->prepare($stt_query);
        $stt_stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
        $stt_stmt->execute();
        $stt_stats = $stt_stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем статистику по анализам (AI баланс)
        $ai_query = "SELECT
            COUNT(DISTINCT ar.callid) as analyzed_calls,
            SUM(cr.duration_sec) as analyzed_duration_sec
        FROM analysis_results ar
        JOIN calls_raw cr ON ar.callid = cr.callid
        WHERE cr.project_id = :project_id";

        $ai_stmt = $db->prepare($ai_query);
        $ai_stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
        $ai_stmt->execute();
        $ai_stats = $ai_stmt->fetch(PDO::FETCH_ASSOC);

        // Подсчитываем баланс STT (транскрибация)
        $stt_total_seconds = floatval($stt_stats['transcribed_duration_sec'] ?? 0);
        $stt_minutes = floor($stt_total_seconds / 60);
        $stt_seconds = round($stt_total_seconds % 60);

        // Подсчитываем баланс AI (анализ)
        $ai_total_seconds = floatval($ai_stats['analyzed_duration_sec'] ?? 0);
        $ai_minutes = floor($ai_total_seconds / 60);
        $ai_seconds = round($ai_total_seconds % 60);

        // Получаем информацию об AI настройках проекта
        $ai_settings_query = "SELECT
            auto_transcription,
            auto_analysis,
            auto_diarization,
            llm_provider,
            llm_model
        FROM project_ai_settings
        WHERE project_id = :project_id
        LIMIT 1";

        $ai_settings_stmt = $db->prepare($ai_settings_query);
        $ai_settings_stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
        $ai_settings_stmt->execute();
        $ai_settings = $ai_settings_stmt->fetch(PDO::FETCH_ASSOC);

        // Формируем ответ
        $response = [
            "success" => true,
            "data" => [
                "project_id" => $project_id,
                "project_name" => $project['name'],
                "stt_balance" => [
                    "minutes" => intval($stt_minutes),
                    "seconds" => intval($stt_seconds),
                    "total_seconds" => $stt_total_seconds,
                    "formatted" => sprintf("%d мин %d сек", $stt_minutes, $stt_seconds)
                ],
                "ai_balance" => [
                    "minutes" => intval($ai_minutes),
                    "seconds" => intval($ai_seconds),
                    "total_seconds" => $ai_total_seconds,
                    "formatted" => sprintf("%d мин %d сек", $ai_minutes, $ai_seconds)
                ],
                "statistics" => [
                    "total_calls" => intval($stats['total_calls'] ?? 0),
                    "total_duration_sec" => floatval($stats['total_duration_sec'] ?? 0),
                    "transcribed_calls" => intval($stt_stats['transcribed_calls'] ?? 0),
                    "analyzed_calls" => intval($ai_stats['analyzed_calls'] ?? 0),
                    "transcription_coverage" => $stats['total_calls'] > 0
                        ? round((intval($stt_stats['transcribed_calls'] ?? 0) / intval($stats['total_calls'])) * 100, 2)
                        : 0,
                    "analysis_coverage" => $stats['total_calls'] > 0
                        ? round((intval($ai_stats['analyzed_calls'] ?? 0) / intval($stats['total_calls'])) * 100, 2)
                        : 0
                ],
                "ai_settings" => $ai_settings ? [
                    "auto_transcription" => boolval($ai_settings['auto_transcription']),
                    "auto_analysis" => boolval($ai_settings['auto_analysis']),
                    "auto_diarization" => boolval($ai_settings['auto_diarization']),
                    "llm_provider" => $ai_settings['llm_provider'],
                    "llm_model" => $ai_settings['llm_model']
                ] : null
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Ошибка запроса: " . $e->getMessage()
        ]);
    }
    exit();
}

// Неподдерживаемый метод
http_response_code(405);
echo json_encode([
    "success" => false,
    "error" => "Метод не поддерживается. Используйте GET."
]);
