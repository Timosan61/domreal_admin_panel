<?php
session_start();
require_once __DIR__ . '/auth/check_auth.php';
checkAuth($require_admin = false);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диагностика загрузки JS</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #00ff00; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #333; }
        .success { background: #002200; color: #00ff00; }
        .error { background: #220000; color: #ff0000; }
        .warning { background: #222200; color: #ffff00; }
        h1 { color: #00ffff; }
        pre { background: #000; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Диагностика загрузки JavaScript</h1>

    <div id="results"></div>

    <script>
        const results = document.getElementById('results');

        function addTest(name, status, message, details = '') {
            const div = document.createElement('div');
            div.className = `test ${status}`;
            div.innerHTML = `
                <strong>${status === 'success' ? '✅' : status === 'error' ? '❌' : '⚠️'} ${name}</strong>
                <div>${message}</div>
                ${details ? `<pre>${details}</pre>` : ''}
            `;
            results.appendChild(div);
        }

        // Test 1: Check if fetchWithRetry exists
        if (typeof fetchWithRetry === 'function') {
            addTest('fetchWithRetry загружен', 'success', 'Функция fetchWithRetry доступна');
        } else {
            addTest('fetchWithRetry НЕ загружен', 'error',
                'Функция fetchWithRetry не найдена! Файл fetch_retry.js не загрузился или заблокирован кэшем.',
                'Решение: Ctrl+Shift+R или откройте в режиме инкогнито');
        }

        // Test 2: Check loaded scripts
        const scripts = Array.from(document.scripts).map(s => s.src).filter(s => s);
        addTest('Загруженные скрипты', 'success',
            `Всего скриптов: ${scripts.length}`,
            scripts.join('\n'));

        // Test 3: Try API request with fetchWithRetry
        if (typeof fetchWithRetry === 'function') {
            fetchWithRetry('/api/analytics/kpi.php?date_from=2025-10-13&date_to=2025-10-20&hide_short_calls=1')
                .then(response => response.json())
                .then(data => {
                    addTest('API запрос (fetchWithRetry)', 'success',
                        'Запрос успешно выполнен',
                        JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    addTest('API запрос (fetchWithRetry)', 'error',
                        'Ошибка при выполнении запроса',
                        error.toString());
                });
        }

        // Test 4: Try API request with regular fetch
        fetch('/api/analytics/kpi.php?date_from=2025-10-13&date_to=2025-10-20&hide_short_calls=1', {
            credentials: 'same-origin'
        })
            .then(response => {
                addTest('API запрос (обычный fetch)', response.ok ? 'success' : 'error',
                    `HTTP ${response.status}: ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                addTest('API ответ', 'success',
                    'Данные успешно получены',
                    JSON.stringify(data, null, 2));
            })
            .catch(error => {
                addTest('API запрос (обычный fetch)', 'error',
                    'Ошибка при выполнении запроса',
                    error.toString());
            });

        // Test 5: Browser cache info
        addTest('Информация о браузере', 'success',
            `User Agent: ${navigator.userAgent}`,
            `Cookies enabled: ${navigator.cookieEnabled}\nOnline: ${navigator.onLine}`);

        // Test 6: Check cookies
        const cookies = document.cookie;
        if (cookies.includes('PHPSESSID')) {
            addTest('PHP Session', 'success', 'PHPSESSID cookie найден');
        } else {
            addTest('PHP Session', 'error', 'PHPSESSID cookie НЕ найден!', 'Сессия не установлена');
        }
    </script>

    <!-- Load fetch_retry.js -->
    <script src="assets/js/fetch_retry.js?v=<?php echo time(); ?>"></script>
</body>
</html>
