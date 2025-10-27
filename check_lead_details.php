<?php
/**
 * Проверка деталей конкретного лида
 */

require_once 'config/database.php';

$leadId = $argv[1] ?? 31;

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT id, source, phone, name, created_at,
               site_name, site_url, page_name, form_name,
               browser, device, platform,
               country, region, city,
               roistat_visit, client_comment,
               quiz_id, quiz_name, quiz_answers, quiz_result,
               ab_test, timezone, lang, cookies, discount, discount_type
        FROM leads
        WHERE id = :id
    ");
    $stmt->execute(['id' => $leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        die("❌ Лид ID $leadId не найден\n");
    }

    echo "=== Лид ID: {$lead['id']} ===\n";
    echo "Source: {$lead['source']}\n";
    echo "Phone: {$lead['phone']}\n";
    echo "Name: {$lead['name']}\n";
    echo "Created: {$lead['created_at']}\n\n";

    echo "🌐 Информация о сайте:\n";
    echo "  site_name: " . ($lead['site_name'] ?: 'NULL') . "\n";
    echo "  site_url: " . ($lead['site_url'] ?: 'NULL') . "\n";
    echo "  page_name: " . ($lead['page_name'] ?: 'NULL') . "\n";
    echo "  form_name: " . ($lead['form_name'] ?: 'NULL') . "\n\n";

    echo "💻 Устройство:\n";
    echo "  browser: " . ($lead['browser'] ?: 'NULL') . "\n";
    echo "  device: " . ($lead['device'] ?: 'NULL') . "\n";
    echo "  platform: " . ($lead['platform'] ?: 'NULL') . "\n\n";

    echo "🌍 Геолокация:\n";
    echo "  country: " . ($lead['country'] ?: 'NULL') . "\n";
    echo "  region: " . ($lead['region'] ?: 'NULL') . "\n";
    echo "  city: " . ($lead['city'] ?: 'NULL') . "\n\n";

    echo "📊 Tracking:\n";
    echo "  roistat_visit: " . ($lead['roistat_visit'] ?: 'NULL') . "\n";
    echo "  client_comment: " . ($lead['client_comment'] ?: 'NULL') . "\n\n";

    echo "🎯 Квиз:\n";
    echo "  quiz_id: " . ($lead['quiz_id'] ?: 'NULL') . "\n";
    echo "  quiz_name: " . ($lead['quiz_name'] ?: 'NULL') . "\n";

    if ($lead['quiz_answers']) {
        $answers = json_decode($lead['quiz_answers'], true);
        echo "  quiz_answers:\n";
        foreach ($answers as $a) {
            echo "    - {$a['q']}: {$a['a']}\n";
        }
    } else {
        echo "  quiz_answers: NULL\n";
    }

    if ($lead['quiz_result']) {
        $result = json_decode($lead['quiz_result'], true);
        echo "  quiz_result:\n";
        echo "    - title: " . ($result['title'] ?? 'N/A') . "\n";
        echo "    - cost: " . ($result['cost'] ?? 'N/A') . "\n";
    } else {
        echo "  quiz_result: NULL\n";
    }
    echo "\n";

    echo "🔧 Дополнительно:\n";
    echo "  ab_test: " . ($lead['ab_test'] ?: 'NULL') . "\n";
    echo "  timezone: " . ($lead['timezone'] ?: 'NULL') . "\n";
    echo "  lang: " . ($lead['lang'] ?: 'NULL') . "\n";
    echo "  discount: " . ($lead['discount'] ?: 'NULL') . "\n";
    echo "  discount_type: " . ($lead['discount_type'] ?: 'NULL') . "\n";

    if ($lead['cookies']) {
        $cookies = json_decode($lead['cookies'], true);
        echo "  cookies:\n";
        foreach ($cookies as $k => $v) {
            echo "    - $k: $v\n";
        }
    } else {
        echo "  cookies: NULL\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
