<!DOCTYPE html>
<html>
<head>
    <title>Analytics API Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #fff; }
        .endpoint { margin: 10px 0; padding: 10px; background: #2d2d2d; border-radius: 4px; }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
    </style>
</head>
<body>
    <h1>🔍 Analytics API Debug Test</h1>
    <p>Testing all analytics endpoints...</p>
    <div id="results"></div>

    <script>
        const endpoints = [
            '/api/analytics/kpi.php',
            '/api/analytics/funnel.php',
            '/api/analytics/dynamics.php',
            '/api/analytics/departments.php',
            '/api/analytics/managers.php',
            '/api/analytics/script_quality.php',
            '/api/analytics/first_call_scores.php',
            '/api/analytics/repeat_call_scores.php',
            '/api/analytics/first_call_results.php',
            '/api/analytics/repeat_call_results.php',
            '/api/analytics/first_call_conversion.php'
        ];

        const resultsDiv = document.getElementById('results');

        async function testEndpoint(url) {
            const startTime = Date.now();
            try {
                const response = await fetch(url);
                const duration = Date.now() - startTime;
                const data = await response.json();

                const status = data.success ? 'success' : 'error';
                const icon = data.success ? '✅' : '❌';
                const message = data.success
                    ? `OK (${duration}ms)`
                    : `FAILED: ${data.error || 'Unknown error'}`;

                return `<div class="endpoint ${status}">${icon} <strong>${url}</strong>: ${message}</div>`;
            } catch (error) {
                const duration = Date.now() - startTime;
                return `<div class="endpoint error">💥 <strong>${url}</strong>: EXCEPTION (${duration}ms)<br>&nbsp;&nbsp;&nbsp;&nbsp;${error.message}</div>`;
            }
        }

        async function testAll() {
            resultsDiv.innerHTML = '<p class="warning">⏳ Testing endpoints...</p>';

            const results = [];
            for (const endpoint of endpoints) {
                const result = await testEndpoint(endpoint);
                results.push(result);
                resultsDiv.innerHTML = results.join('');
            }

            resultsDiv.innerHTML += '<p class="success">✅ All tests completed!</p>';
        }

        testAll();
    </script>
</body>
</html>
