<?php
/**
 * API endpoint для получения списка кандидатов из HR-бота AILOCA
 *
 * GET ?action=list
 * GET ?action=get&id=123
 *
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "telegram_id": 123456789,
 *       "telegram_username": "user123",
 *       "full_name": "Иван Петров",
 *       "phone": "+79991234567",
 *       "email": "ivan@example.com",
 *       "status": "module1_in_progress",
 *       "status_label": "Модуль 1 в процессе",
 *       "module1_score": 80,
 *       "module2_score": null,
 *       "voice_score": null,
 *       "created_at": "2025-12-13 10:00:00",
 *       "has_resume": true
 *     }
 *   ]
 * }
 *
 * @author Claude Code
 * @date 2025-12-13
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Создаем подключение к БД
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Не удалось подключиться к базе данных'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Status labels mapping
$statusLabels = [
    'registered' => '📝 Зарегистрирован',
    'module1_in_progress' => '📚 Модуль 1',
    'module1_completed' => '✅ Модуль 1 пройден',
    'module1_failed' => '❌ Модуль 1 провален',
    'module2_in_progress' => '📞 Модуль 2',
    'module2_completed' => '✅ Модуль 2 пройден',
    'module2_failed' => '❌ Модуль 2 провален',
    'module3_in_progress' => '🎤 Практика',
    'module3_completed' => '✅ Практика пройдена',
    'module3_failed' => '❌ Практика провалена',
    'interview_scheduled' => '📅 Ждёт собеседования',
    'interview_completed' => '🎉 Собеседование пройдено',
    'hired' => '🏆 Нанят',
    'soft_rejected' => '⏸ Мягкий отказ',
    'hard_rejected' => '🚫 Отказ',
];

if ($method === 'GET' && $action === 'list') {
    try {
        // SQL запрос для получения списка кандидатов с результатами тестов
        $sql = "SELECT
            c.id,
            c.telegram_id,
            c.telegram_username,
            c.full_name,
            c.phone,
            c.email,
            c.status,
            c.current_module,
            c.module1_attempts,
            c.module2_attempts,
            c.module3_attempts,
            c.resume_path,
            c.interview_datetime,
            c.created_at,
            c.updated_at,
            -- Результаты теста модуль 1
            (SELECT score_percent FROM hr_quiz_results
             WHERE candidate_id = c.id AND module_number = 1 AND passed = 1
             ORDER BY completed_at DESC LIMIT 1) as module1_score,
            -- Результаты теста модуль 2
            (SELECT score_percent FROM hr_quiz_results
             WHERE candidate_id = c.id AND module_number = 2 AND passed = 1
             ORDER BY completed_at DESC LIMIT 1) as module2_score,
            -- Результат голосового задания
            (SELECT score FROM hr_voice_tasks
             WHERE candidate_id = c.id AND passed = 1
             ORDER BY submitted_at DESC LIMIT 1) as voice_score
        FROM hr_candidates c
        ORDER BY c.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Форматирование данных
        foreach ($candidates as &$candidate) {
            $candidate['id'] = intval($candidate['id']);
            $candidate['telegram_id'] = intval($candidate['telegram_id']);
            $candidate['module1_score'] = $candidate['module1_score'] ? intval($candidate['module1_score']) : null;
            $candidate['module2_score'] = $candidate['module2_score'] ? intval($candidate['module2_score']) : null;
            $candidate['voice_score'] = $candidate['voice_score'] ? intval($candidate['voice_score']) : null;
            $candidate['status_label'] = $statusLabels[$candidate['status']] ?? $candidate['status'];
            $candidate['has_resume'] = !empty($candidate['resume_path']);

            // Вычисляем общий прогресс
            $progress = 0;
            if (strpos($candidate['status'], 'module1') !== false) {
                $progress = 25;
            } elseif (strpos($candidate['status'], 'module2') !== false) {
                $progress = 50;
            } elseif (strpos($candidate['status'], 'module3') !== false) {
                $progress = 75;
            } elseif (in_array($candidate['status'], ['interview_scheduled', 'interview_completed', 'hired'])) {
                $progress = 100;
            }
            $candidate['overall_progress'] = $progress;
        }

        echo json_encode([
            'success' => true,
            'data' => $candidates,
            'total' => count($candidates)
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка БД: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} elseif ($method === 'GET' && $action === 'get') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID кандидата обязателен'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Получаем кандидата
        $sql = "SELECT * FROM hr_candidates WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$candidate) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Кандидат не найден'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $candidate['status_label'] = $statusLabels[$candidate['status']] ?? $candidate['status'];

        // Получаем результаты тестов
        $sql = "SELECT * FROM hr_quiz_results WHERE candidate_id = :id ORDER BY completed_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $candidate['quiz_results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Получаем голосовые задания
        $sql = "SELECT * FROM hr_voice_tasks WHERE candidate_id = :id ORDER BY submitted_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $candidate['voice_tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Получаем события
        $sql = "SELECT * FROM hr_candidate_events WHERE candidate_id = :id ORDER BY created_at DESC LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $candidate['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $candidate
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка БД: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} elseif ($method === 'GET' && $action === 'stats') {
    try {
        // Статистика по статусам
        $sql = "SELECT status, COUNT(*) as count FROM hr_candidates GROUP BY status";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $statusStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Общая статистика
        $sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired,
            SUM(CASE WHEN status IN ('interview_scheduled', 'interview_completed') THEN 1 ELSE 0 END) as interviews,
            SUM(CASE WHEN status LIKE '%failed%' OR status LIKE '%rejected%' THEN 1 ELSE 0 END) as rejected
        FROM hr_candidates";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'total' => $totalStats,
                'by_status' => $statusStats
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка БД: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Неверный метод или action'
    ], JSON_UNESCAPED_UNICODE);
}
