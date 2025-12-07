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

// Получаем путь к аудиофайлу и record_url из Beeline
$query = "SELECT
            aj.local_path,
            aj.file_format,
            aj.file_size_bytes,
            aj.status,
            cr.record_url
          FROM audio_jobs aj
          LEFT JOIN calls_raw cr ON aj.callid = cr.callid
          WHERE aj.callid = :callid
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

// Убираем проверку статуса DONE, так как теперь мы можем скачать файл из Beeline
// даже если локальная обработка не завершена

// Стратегия поиска файла:
// 1. Проверяем основное хранилище (audio_files/)
// 2. Проверяем временный кеш (temp_audio_cache/)
// 3. Скачиваем из Beeline и сохраняем в кеш

$base_path = '/home/z/ailoca/Domreal_Whisper/';
$cache_path = $base_path . 'temp_audio_cache/';
$file_path = null;
$file_format = $audio['file_format'] ?? 'mp3';

// Шаг 1: Проверяем основное хранилище
$main_path = $audio['local_path'];
if ($main_path && $main_path[0] !== '/') {
    $main_path = $base_path . $main_path;
}

if ($main_path && file_exists($main_path)) {
    $file_path = $main_path;
} else {
    // Шаг 2: Проверяем кеш
    $cached_file = $cache_path . $callid . '.wav';
    if (file_exists($cached_file)) {
        $file_path = $cached_file;
    } else {
        // Шаг 3: Скачиваем из Beeline
        if (empty($audio['record_url'])) {
            http_response_code(404);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "error" => "Audio file not available",
                "details" => "No record URL found in database"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Скачиваем файл из Beeline API используя file_get_contents
        $beeline_token = "10678467-1e6f-4062-87b9-77a2d6d94411";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "X-MPBX-API-AUTH-TOKEN: " . $beeline_token,
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);

        $audio_data = @file_get_contents($audio['record_url'], false, $context);

        // Проверяем HTTP код ответа
        $http_code = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $http_code = (int)$matches[1];
                    break;
                }
            }
        }

        if ($http_code !== 200 || !$audio_data) {
            http_response_code(503);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "error" => "Failed to download audio from Beeline",
                "http_code" => $http_code,
                "record_url" => $audio['record_url']
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Сохраняем в кеш
        file_put_contents($cached_file, $audio_data);
        $file_path = $cached_file;
        $file_format = 'mp3'; // Beeline отдает MP3
    }
}

// Финальная проверка существования файла
if (!$file_path || !file_exists($file_path)) {
    http_response_code(404);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "error" => "Audio file not found",
        "attempted_path" => $file_path
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

$file_format = strtolower($file_format);
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
