<?php
/**
 * Тест доступа к API enrichment_data
 */
session_start();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>API Test</title></head><body>";
echo "<h1>Тест API enrichment_data.php</h1>";

echo "<h2>1. Проверка сессии:</h2>";
echo "<pre>";
echo "Сессия активна: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ ДА" : "❌ НЕТ") . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? '❌ НЕТ') . "\n";
echo "Role: " . ($_SESSION['role'] ?? '❌ НЕТ') . "\n";
echo "Full Name: " . ($_SESSION['full_name'] ?? '❌ НЕТ') . "\n";
echo "</pre>";

echo "<h2>2. Прямой вызов API:</h2>";

// Тест 1: Статистика
echo "<h3>Тест статистики (stats=1):</h3>";
$stats_url = "http://localhost/admin_panel/api/enrichment_data.php?stats=1";
$context = stream_context_create([
    'http' => [
        'header' => "Cookie: " . session_name() . "=" . session_id() . "\r\n"
    ]
]);
$stats_response = @file_get_contents($stats_url, false, $context);
if ($stats_response) {
    echo "<pre>" . htmlspecialchars(substr($stats_response, 0, 500)) . "...</pre>";
} else {
    echo "<p style='color: red;'>❌ Ошибка загрузки статистики</p>";
}

// Тест 2: Данные
echo "<h3>Тест данных (page=1):</h3>";
$data_url = "http://localhost/admin_panel/api/enrichment_data.php?page=1&per_page=5";
$data_response = @file_get_contents($data_url, false, $context);
if ($data_response) {
    echo "<pre>" . htmlspecialchars(substr($data_response, 0, 500)) . "...</pre>";
} else {
    echo "<p style='color: red;'>❌ Ошибка загрузки данных</p>";
}

echo "<h2>3. JavaScript тест:</h2>";
echo "<button onclick='testFetch()'>Тест Fetch API</button>";
echo "<div id='fetch-result'></div>";

echo "<script>
function testFetch() {
    const resultDiv = document.getElementById('fetch-result');
    resultDiv.innerHTML = '<p>Загрузка...</p>';

    fetch('api/enrichment_data.php?stats=1', {
        credentials: 'same-origin'
    })
    .then(response => {
        resultDiv.innerHTML = '<p>Status: ' + response.status + ' ' + response.statusText + '</p>';
        return response.json();
    })
    .then(data => {
        resultDiv.innerHTML += '<pre>' + JSON.stringify(data, null, 2).substring(0, 500) + '...</pre>';
    })
    .catch(error => {
        resultDiv.innerHTML = '<p style=\"color: red;\">❌ Ошибка: ' + error.message + '</p>';
    });
}
</script>";

echo "</body></html>";
?>
