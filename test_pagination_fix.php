<?php
/**
 * Тест исправлений пагинации
 * Проверка: session_write_close() работает корректно
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧪 Тест исправлений пагинации Money Tracker</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } .success { color: green; } .error { color: red; }</style>";

// Тест 1: Проверка session_write_close в API
echo "<h2>Тест 1: Проверка session_write_close() в API</h2>";

$api_file = __DIR__ . '/api/enrichment_data.php';
$content = file_get_contents($api_file);

if (strpos($content, 'session_write_close()') !== false) {
    echo "<p class='success'>✅ session_write_close() добавлен в API</p>";
} else {
    echo "<p class='error'>❌ session_write_close() НЕ найден в API</p>";
}

// Тест 2: Проверка расположения session_write_close
$lines = explode("\n", $content);
$session_start_line = 0;
$session_close_line = 0;
$check_auth_line = 0;

foreach ($lines as $num => $line) {
    if (strpos($line, 'session_start()') !== false) {
        $session_start_line = $num + 1;
    }
    if (strpos($line, 'session_write_close()') !== false) {
        $session_close_line = $num + 1;
    }
    if (strpos($line, 'checkAuth(') !== false) {
        $check_auth_line = $num + 1;
    }
}

echo "<p>session_start() на строке: <strong>$session_start_line</strong></p>";
echo "<p>checkAuth() на строке: <strong>$check_auth_line</strong></p>";
echo "<p>session_write_close() на строке: <strong>$session_close_line</strong></p>";

if ($session_close_line > $check_auth_line && $check_auth_line > $session_start_line) {
    echo "<p class='success'>✅ Правильная последовательность: session_start → checkAuth → session_write_close</p>";
} else {
    echo "<p class='error'>❌ Неправильная последовательность вызовов</p>";
}

// Тест 3: Проверка JavaScript защиты
echo "<h2>Тест 2: Проверка JavaScript защиты от race condition</h2>";

$js_file = __DIR__ . '/assets/js/money_tracker.js';
$js_content = file_get_contents($js_file);

$checks = [
    'isLoading = false' => 'Флаг isLoading объявлен',
    'if (isLoading)' => 'Проверка isLoading в loadEnrichmentData()',
    'isLoading = true' => 'Установка isLoading = true',
    'updatePaginationButtonsState' => 'Функция updatePaginationButtonsState существует'
];

foreach ($checks as $search => $description) {
    if (strpos($js_content, $search) !== false) {
        echo "<p class='success'>✅ $description</p>";
    } else {
        echo "<p class='error'>❌ $description - НЕ НАЙДЕНО</p>";
    }
}

// Тест 4: Симуляция множественных запросов
echo "<h2>Тест 3: Симуляция множественных запросов</h2>";
echo "<p>Открой консоль браузера (F12) и нажми кнопки ниже:</p>";

?>
<script>
let testRequestCounter = 0;

async function testSingleRequest() {
    testRequestCounter++;
    const reqId = testRequestCounter;

    console.log(`[TEST] Запрос #${reqId} отправлен в ${new Date().toLocaleTimeString()}`);

    const start = performance.now();
    const response = await fetch('/admin_panel/api/enrichment_data.php?page=1&per_page=10', {
        credentials: 'same-origin'
    });
    const elapsed = Math.round(performance.now() - start);

    console.log(`[TEST] Запрос #${reqId} завершен за ${elapsed}ms`);

    const data = await response.json();
    if (data.success) {
        document.getElementById('test-results').innerHTML +=
            `<p class="success">✅ Запрос #${reqId} успешен (${elapsed}ms, ${data.data.length} записей)</p>`;
    } else {
        document.getElementById('test-results').innerHTML +=
            `<p class="error">❌ Запрос #${reqId} ошибка: ${data.error}</p>`;
    }
}

async function testMultipleRequests() {
    console.log('[TEST] === Запуск 5 параллельных запросов ===');
    document.getElementById('test-results').innerHTML = '<p><strong>Отправляю 5 параллельных запросов...</strong></p>';

    // Отправляем 5 запросов одновременно (имитация быстрых кликов)
    Promise.all([
        testSingleRequest(),
        testSingleRequest(),
        testSingleRequest(),
        testSingleRequest(),
        testSingleRequest()
    ]).then(() => {
        document.getElementById('test-results').innerHTML +=
            '<p class="success"><strong>✅ Все запросы завершены! Если все запросы выполнились быстро (~100-300ms каждый), исправление работает.</strong></p>';
    });
}
</script>

<button onclick="testSingleRequest()" style="padding: 10px 20px; margin: 5px;">Одиночный запрос</button>
<button onclick="testMultipleRequests()" style="padding: 10px 20px; margin: 5px; background: orange; color: white;">5 параллельных запросов (тест race condition)</button>

<div id="test-results" style="margin-top: 20px; padding: 10px; background: #f5f5f5; border-radius: 5px;"></div>

<?php
echo "<h2>📋 Итоги тестирования</h2>";
echo "<ol>";
echo "<li>Проверь что session_write_close() добавлен ✅</li>";
echo "<li>Проверь что JavaScript защита работает ✅</li>";
echo "<li>Нажми \"5 параллельных запросов\" и проверь что ВСЕ запросы выполняются быстро (~100-300ms)</li>";
echo "<li>Если все запросы быстрые - исправление работает!</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Теперь открой страницу Money Tracker и попробуй быстро кликать \"Вперед\" несколько раз. Зависания быть не должно!</strong></p>";
?>
