<?php
/**
 * API endpoint для получения списка активных шаблонов анализа
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/database.php';

try {
    // Получаем активные шаблоны
    $stmt = $pdo->prepare("
        SELECT
            template_id,
            name,
            template_type,
            is_system
        FROM analysis_templates
        WHERE is_active = 1
          AND org_id = 'org-legacy'
        ORDER BY
            is_system DESC,
            created_at ASC
    ");

    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'templates' => $templates,
        'count' => count($templates)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
