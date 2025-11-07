<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест API</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Тест API доступности</h1>
    <div id="status">Загрузка...</div>
    <h2>Результат:</h2>
    <pre id="result"></pre>

    <script>
        const statusDiv = document.getElementById('status');
        const resultPre = document.getElementById('result');

        // Тест 1: Статистика
        console.log('Запрос к /api/enrichment_data.php?stats=1');

        fetch('/api/enrichment_data.php?stats=1', {
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Статус ответа:', response.status);
            console.log('Headers:', response.headers);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            statusDiv.innerHTML = '<span class="success">✅ API доступен!</span>';
            resultPre.textContent = JSON.stringify(data, null, 2);
        })
        .catch(error => {
            statusDiv.innerHTML = `<span class="error">❌ Ошибка: ${error.message}</span>`;
            resultPre.textContent = `Error: ${error}\n\nStack: ${error.stack}`;
        });
    </script>
</body>
</html>
