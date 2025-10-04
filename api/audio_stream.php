<?php
/**
 * API для стриминга аудиофайла звонка
 * GET /api/audio_stream.php?callid=xxx
 *
 * ВАЖНО: Этот endpoint предполагает, что аудиофайлы хранятся локально.
 * Если файлы хранятся на удаленном сервере, нужно будет добавить логику загрузки.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

// Получаем callid
$callid = isset($_GET['callid']) ? $_GET['callid'] : '';

if (empty($callid)) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => "Parameter 'callid' is required"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Подключаемся к БД
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Получаем путь к аудиофайлу
$query = "SELECT local_path, file_format, file_size_bytes, status
          FROM audio_jobs
          WHERE callid = :callid
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':callid', $callid);
$stmt->execute();

$audio = $stmt->fetch();

if (!$audio) {
    http_response_code(404);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => "Audio file not found"], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($audio['status'] !== 'DONE') {
    http_response_code(404);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "error" => "Audio file is not ready",
        "status" => $audio['status']
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$file_path = $audio['local_path'];

// Проверяем, существует ли файл
if (!file_exists($file_path)) {
    http_response_code(404);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "error" => "Audio file not found on disk",
        "path" => $file_path
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Определяем MIME тип
$mime_types = [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'flac' => 'audio/flac',
    'm4a' => 'audio/mp4'
];

$file_format = strtolower($audio['file_format']);
$mime_type = isset($mime_types[$file_format]) ? $mime_types[$file_format] : 'application/octet-stream';

// Отправляем заголовки для стриминга
header("Content-Type: " . $mime_type);
header("Content-Length: " . filesize($file_path));
header("Accept-Ranges: bytes");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Поддержка Range запросов для перемотки
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $range_parts = explode('-', $range);
    $start = intval($range_parts[0]);
    $end = isset($range_parts[1]) && $range_parts[1] !== '' ? intval($range_parts[1]) : filesize($file_path) - 1;
    $length = $end - $start + 1;

    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/" . filesize($file_path));
    header("Content-Length: $length");

    $fp = fopen($file_path, 'rb');
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
} else {
    // Обычная отправка файла
    readfile($file_path);
}

exit();
