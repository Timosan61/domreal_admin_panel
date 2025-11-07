<?php
/**
 * Тест маппинга GCK
 */

require_once 'config/database.php';
require_once 'lidtracker/classes/WebhookReceiver.php';

class GckReceiverTest extends WebhookReceiver {
    protected function extractPhone($data) {
        if (isset($data['phones']) && is_array($data['phones']) && !empty($data['phones'])) {
            return $data['phones'][0];
        }
        return null;
    }

    protected function mapFields($data) {
        $mapped = [];
        $mapped['name'] = $data['name'] ?? null;

        if (isset($data['mails']) && is_array($data['mails']) && !empty($data['mails'])) {
            $mapped['email'] = $data['mails'][0];
        } else {
            $mapped['email'] = null;
        }

        // Новые поля
        $mapped['browser'] = $data['browser'] ?? null;
        $mapped['device'] = $data['device'] ?? null;
        $mapped['platform'] = $data['platform'] ?? null;
        $mapped['country'] = $data['country'] ?? null;
        $mapped['region'] = $data['region'] ?? null;
        $mapped['city'] = $data['city'] ?? null;
        $mapped['site_name'] = $data['site'] ?? null;
        $mapped['roistat_visit'] = $data['roistat_visit'] ?? null;
        $mapped['client_comment'] = $data['comment'] ?? null;

        return $mapped;
    }
}

$testData = [
    'vid' => 999888777,
    'phones' => ['+79261234567'],
    'name' => 'Мария Петрова',
    'mails' => ['maria.petrova@gmail.com'],
    'browser' => 'Chrome 120',
    'device' => 'Desktop',
    'platform' => 'Windows 10',
    'country' => 'Россия',
    'region' => 'Москва',
    'city' => 'Москва',
    'site' => 'marinagardensochi.ru',
    'roistat_visit' => '12345',
    'comment' => 'Интересует квартира с видом на море'
];

echo "=== Тест GCK mapFields() ===\n\n";

$database = new Database();
$db = $database->getConnection();

$receiver = new GckReceiverTest($db, 'gck');

// Используем Reflection чтобы вызвать protected метод
$reflection = new ReflectionClass($receiver);
$method = $reflection->getMethod('mapFields');
$method->setAccessible(true);

$mapped = $method->invoke($receiver, $testData);

echo "📊 Результат маппинга:\n";
print_r($mapped);

echo "\n\n🔍 Проверка ключевых полей:\n";
echo "  name: " . ($mapped['name'] ?? 'NULL') . "\n";
echo "  email: " . ($mapped['email'] ?? 'NULL') . "\n";
echo "  browser: " . ($mapped['browser'] ?? 'NULL') . "\n";
echo "  device: " . ($mapped['device'] ?? 'NULL') . "\n";
echo "  platform: " . ($mapped['platform'] ?? 'NULL') . "\n";
echo "  country: " . ($mapped['country'] ?? 'NULL') . "\n";
echo "  city: " . ($mapped['city'] ?? 'NULL') . "\n";
echo "  site_name: " . ($mapped['site_name'] ?? 'NULL') . "\n";
echo "  roistat_visit: " . ($mapped['roistat_visit'] ?? 'NULL') . "\n";
echo "  client_comment: " . ($mapped['client_comment'] ?? 'NULL') . "\n";
