<?php
/**
 * Веб-интерфейс для мониторинга Creatium webhook
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creatium Webhook Monitor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: #2a2a2a;
            border: 2px solid #00ff00;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .status {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .status-item {
            background: #1a1a1a;
            padding: 10px 15px;
            border-radius: 3px;
            border: 1px solid #444;
        }
        .log-container {
            background: #2a2a2a;
            border: 2px solid #00ff00;
            padding: 20px;
            border-radius: 5px;
            min-height: 500px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .log-entry {
            margin-bottom: 15px;
            padding: 10px;
            background: #1a1a1a;
            border-left: 3px solid #00ff00;
        }
        .log-entry.error {
            border-left-color: #ff0000;
            color: #ff6666;
        }
        .log-entry.success {
            border-left-color: #00ff00;
        }
        .timestamp {
            color: #888;
            font-size: 12px;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #00ff00;
            color: #1a1a1a;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            border-radius: 3px;
        }
        .btn:hover {
            background: #00cc00;
        }
        .btn.danger {
            background: #ff0000;
            color: white;
        }
        .auto-refresh {
            color: #ffff00;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        .empty-log {
            text-align: center;
            padding: 50px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔴 WEBHOOK MONITOR (ALL)</h1>
            <div style="margin-top: 10px;">
                <strong>Creatium:</strong> https://domrilhost.ru:18080/webhook/creatium.php<br>
                <strong>GCK:</strong> https://domrilhost.ru:18080/webhook/gck.php<br>
                <strong>Marquiz:</strong> https://domrilhost.ru:18080/webhook/marquiz.php
            </div>
            <div class="status">
                <div class="status-item">
                    <strong>Время:</strong> <span id="current-time"></span>
                </div>
                <div class="status-item">
                    <strong>Статус:</strong> <span class="auto-refresh" id="status">🔴 ОЖИДАНИЕ</span>
                </div>
                <div class="status-item">
                    <strong>Всего запросов:</strong> <span id="request-count">0</span>
                </div>
            </div>
        </div>

        <div class="controls">
            <button class="btn" onclick="refreshLog()">🔄 Обновить</button>
            <button class="btn danger" onclick="clearLog()">🗑️ Очистить лог</button>
            <label style="color: white; margin-left: 20px;">
                <input type="checkbox" id="auto-refresh" checked> Авто-обновление (3 сек)
            </label>
        </div>

        <div class="log-container" id="log-container">
            <div class="empty-log">Загрузка логов...</div>
        </div>
    </div>

    <script>
        let requestCount = 0;

        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('ru-RU');
        }

        function refreshLog() {
            fetch('get_log.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('log-container');

                    if (data.entries.length === 0) {
                        container.innerHTML = '<div class="empty-log">Нет записей. Ожидание запросов...</div>';
                        requestCount = 0;
                    } else {
                        container.innerHTML = '';
                        data.entries.forEach(entry => {
                            const div = document.createElement('div');
                            div.className = 'log-entry ' + entry.type;

                            const sourceIcon = entry.source === 'Creatium' ? '🌐' :
                                             entry.source === 'GCK' ? '💻' : '🎯';

                            div.innerHTML = `
                                <div class="timestamp">${sourceIcon} <strong>${entry.source}</strong> | ${entry.timestamp}</div>
                                <div>${entry.content}</div>
                            `;
                            container.appendChild(div);
                        });
                        requestCount = data.count;

                        // Обновляем статус
                        const lastEntry = data.entries[data.entries.length - 1];
                        if (lastEntry.type === 'success') {
                            document.getElementById('status').textContent = '✅ АКТИВЕН';
                            document.getElementById('status').className = '';
                        }
                    }

                    document.getElementById('request-count').textContent = requestCount;

                    // Прокрутка вниз
                    container.scrollTop = container.scrollHeight;
                })
                .catch(error => {
                    console.error('Ошибка загрузки лога:', error);
                });
        }

        function clearLog() {
            if (confirm('Очистить весь лог?')) {
                fetch('clear_log.php', { method: 'POST' })
                    .then(() => refreshLog());
            }
        }

        // Обновление времени каждую секунду
        setInterval(updateTime, 1000);
        updateTime();

        // Авто-обновление
        setInterval(() => {
            if (document.getElementById('auto-refresh').checked) {
                refreshLog();
            }
        }, 3000);

        // Первая загрузка
        refreshLog();
    </script>
</body>
</html>
