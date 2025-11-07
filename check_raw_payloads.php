<?php
/**
 * Проверка raw_payload последних лидов
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "=== Проверка raw_payload последних лидов ===\n\n";

    $stmt = $db->query("
        SELECT id, source, phone, created_at, raw_payload
        FROM leads
        ORDER BY created_at DESC
        LIMIT 5
    ");

    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($leads as $lead) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: " . $lead['id'] . " | Source: " . $lead['source'] . " | Created: " . $lead['created_at'] . "\n";
        echo "Phone: " . $lead['phone'] . "\n\n";

        if ($lead['raw_payload']) {
            $payload = json_decode($lead['raw_payload'], true);
            if ($payload) {
                echo "📦 Raw Payload (форматированный):\n";
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "⚠️  Raw Payload (не JSON):\n";
                echo substr($lead['raw_payload'], 0, 500) . "\n";
            }
        } else {
            echo "⚠️  Raw Payload отсутствует\n";
        }

        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
