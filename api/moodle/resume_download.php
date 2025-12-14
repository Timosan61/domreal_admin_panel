<?php
/**
 * API endpoint для скачивания резюме студента из Moodle
 *
 * GET ?user_id=123
 *
 * Скачивает файл резюме, сохраненный через Moodle File API
 *
 * @author Claude Code
 * @date 2025-12-12
 */

// Error handling - отключаем вывод ошибок в output (они испортят файл)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/moodle_database.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан user_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $moodle_db = new MoodleDatabase();
    $conn = $moodle_db->getConnection();

    if (!$conn) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к Moodle БД'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Получаем информацию о резюме из таблицы регистраций
    $sql = "SELECT resume_filename, resume_itemid
            FROM mdl_local_pubreg_log
            WHERE user_id = :user_id
            AND resume_filename IS NOT NULL
            ORDER BY timecreated DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $resume_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resume_info || empty($resume_info['resume_filename'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Резюме не найдено'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Получаем файл из Moodle File API (таблица mdl_files)
    // Component: local_public_registration, filearea: resume, itemid: user_id
    $sql_file = "SELECT f.id, f.contenthash, f.filename, f.filesize, f.mimetype
                 FROM mdl_files f
                 WHERE f.component = 'local_public_registration'
                   AND f.filearea = 'resume'
                   AND f.itemid = :itemid
                   AND f.filename != '.'
                 ORDER BY f.timecreated DESC
                 LIMIT 1";

    $stmt_file = $conn->prepare($sql_file);
    $itemid = $resume_info['resume_itemid'] ?? $user_id;
    $stmt_file->bindParam(':itemid', $itemid, PDO::PARAM_INT);
    $stmt_file->execute();
    $file_record = $stmt_file->fetch(PDO::FETCH_ASSOC);

    if (!$file_record) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Файл не найден в хранилище Moodle'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Построение пути к файлу в Moodle filedir
    // Moodle хранит файлы по хешу: filedir/XX/YY/HASH где XX - первые 2 символа, YY - следующие 2
    $contenthash = $file_record['contenthash'];
    $moodledata_path = '/home/z/PROJECT/Moodle/moodledata';
    $file_path = $moodledata_path . '/filedir/'
               . substr($contenthash, 0, 2) . '/'
               . substr($contenthash, 2, 2) . '/'
               . $contenthash;

    if (!file_exists($file_path)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Физический файл не найден',
            'debug' => $file_path
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Определяем MIME-тип
    $mimetype = $file_record['mimetype'] ?: 'application/octet-stream';
    $filename = $file_record['filename'];
    $filesize = filesize($file_path);

    // Отправляем файл
    header('Content-Type: ' . $mimetype);
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Очищаем буфер и отправляем файл
    ob_clean();
    flush();
    readfile($file_path);
    exit;

} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка БД: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
