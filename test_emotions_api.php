<?php
/**
 * Тестовый скрипт для проверки API эмоций
 */

require_once __DIR__ . '/config/database.php';

$callid = $_GET['callid'] ?? '338257178';

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            callid,
            emotion_data,
            LENGTH(emotion_data) as data_length,
            JSON_VALID(emotion_data) as is_valid_json
        FROM analysis_results
        WHERE callid = :callid
        LIMIT 1
    ");

    $stmt->execute(['callid' => $callid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo "❌ Call not found: {$callid}\n";
        exit(1);
    }

    echo "✅ Call found: {$callid}\n";
    echo "Data length: {$result['data_length']} bytes\n";
    echo "Valid JSON: " . ($result['is_valid_json'] ? 'YES' : 'NO') . "\n\n";

    if ($result['emotion_data']) {
        $emotion = json_decode($result['emotion_data'], true);

        echo "📦 Emotion Data Structure:\n";
        echo "Keys: " . implode(', ', array_keys($emotion)) . "\n\n";

        echo "📋 Full JSON (pretty):\n";
        echo json_encode($emotion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "⚠️ emotion_data is NULL\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: {$e->getMessage()}\n";
    exit(1);
}
