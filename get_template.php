<?php
/**
 * API endpoint для получения деталей шаблона с вопросами
 */
session_start();
require_once 'auth/session.php';
checkAuth(false, true); // API режим

require_once 'config/database.php';

header('Content-Type: application/json');

// Получить ID шаблона из параметров
$template_id = $_GET['id'] ?? null;

if (!$template_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Template ID is required']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception('Database connection failed');
    }

    // Загрузить шаблон
    $query = "SELECT * FROM analysis_templates WHERE template_id = :template_id AND org_id = 'org-legacy' LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':template_id', $template_id);
    $stmt->execute();

    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
        exit();
    }

    // Загрузить вопросы
    $query_questions = "SELECT * FROM analysis_questions
                        WHERE template_id = :template_id
                        ORDER BY question_order ASC";
    $stmt_questions = $db->prepare($query_questions);
    $stmt_questions->bindParam(':template_id', $template_id);
    $stmt_questions->execute();

    $questions = $stmt_questions->fetchAll(PDO::FETCH_ASSOC);

    // Добавить вопросы к шаблону
    $template['questions'] = $questions;

    // Вернуть результат
    echo json_encode($template);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
