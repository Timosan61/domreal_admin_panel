<?php
/**
 * API endpoint для скачивания/стриминга голосовых записей HR Bot
 *
 * GET ?id=123 - стриминг голосового файла по ID voice_task
 *
 * @author Claude Code
 * @date 2025-12-14
 */

require_once __DIR__ . '/../../config/database.php';

// Проверяем ID
$voiceTaskId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$voiceTaskId) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'ID голосового задания обязателен'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подключаемся к БД
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Не удалось подключиться к базе данных'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Получаем путь к файлу из БД
    $sql = "SELECT voice_file_path FROM hr_voice_tasks WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $voiceTaskId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || empty($result['voice_file_path'])) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Голосовой файл не найден'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $relativePath = $result['voice_file_path'];

    // Путь к директории HR Bot (на уровень выше от admin_panel)
    $botBasePath = realpath(__DIR__ . '/../../../ TG_HR_BOT');

    // Полный путь к файлу
    $fullPath = $botBasePath . '/' . ltrim($relativePath, '/');

    // Проверяем существование файла
    if (!file_exists($fullPath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Файл не найден на диске: ' . $relativePath
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Определяем MIME-тип
    $mimeType = 'audio/ogg';
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    if ($extension === 'mp3') {
        $mimeType = 'audio/mpeg';
    } elseif ($extension === 'wav') {
        $mimeType = 'audio/wav';
    } elseif ($extension === 'webm') {
        $mimeType = 'audio/webm';
    }

    // Отправляем файл
    $fileSize = filesize($fullPath);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=3600');
    header('Content-Disposition: inline; filename="voice_' . $voiceTaskId . '.' . $extension . '"');

    // Поддержка range requests для seekable audio
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
        $start = intval($matches[1]);
        $end = $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;

        if ($start > $end || $start >= $fileSize) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }

        $length = $end - $start + 1;

        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . $length);

        $fp = fopen($fullPath, 'rb');
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
    } else {
        readfile($fullPath);
    }

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка БД: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
