<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>API Test</title>
</head>
<body>
    <h1>Dashboard API Test</h1>

    <h2>1. Test API via fetch</h2>
    <button onclick="testAPI()">Test /api/dashboards.php</button>
    <pre id="result"></pre>

    <h2>2. Test analytics API</h2>
    <button onclick="testAnalyticsAPI()">Test /api/analytics/conversion_funnel.php</button>
    <pre id="result2"></pre>

    <script>
        async function testAPI() {
            const resultDiv = document.getElementById('result');
            resultDiv.textContent = 'Loading...';

            try {
                const response = await fetch('/api/dashboards.php?action=list');
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                const text = await response.text();
                console.log('Response text:', text);

                resultDiv.textContent = 'Status: ' + response.status + '\n\n';
                resultDiv.textContent += 'Response:\n' + text;

                // Try to parse as JSON
                try {
                    const json = JSON.parse(text);
                    resultDiv.textContent += '\n\nParsed JSON:\n' + JSON.stringify(json, null, 2);
                } catch (e) {
                    resultDiv.textContent += '\n\nJSON Parse Error: ' + e.message;
                }
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                console.error('Fetch error:', error);
            }
        }

        async function testAnalyticsAPI() {
            const resultDiv = document.getElementById('result2');
            resultDiv.textContent = 'Loading...';

            try {
                const params = new URLSearchParams({
                    date_from: '2025-11-01',
                    date_to: '2025-12-07'
                });

                const response = await fetch('/api/analytics/conversion_funnel.php?' + params);
                const text = await response.text();

                resultDiv.textContent = 'Status: ' + response.status + '\n\n';
                resultDiv.textContent += 'Response:\n' + text;

                try {
                    const json = JSON.parse(text);
                    resultDiv.textContent += '\n\nParsed JSON:\n' + JSON.stringify(json, null, 2);
                } catch (e) {
                    resultDiv.textContent += '\n\nJSON Parse Error: ' + e.message;
                }
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
            }
        }
    </script>
</body>
</html>
