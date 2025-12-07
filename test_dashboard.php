<?php
session_start();
require_once 'auth/session.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Test</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 15px;
            background: #f0f0f0;
            padding: 20px;
            min-height: 400px;
        }
        .widget {
            background: white;
            border: 2px solid #2196F3;
            border-radius: 6px;
            padding: 15px;
            min-height: 100px;
        }
        .widget-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            text-align: center;
            background: #e3f2fd;
            padding: 8px;
        }
        .widget-kpi {
            text-align: center;
        }
        .kpi-value {
            font-size: 32px;
            font-weight: 600;
            color: #2196F3;
            margin: 10px 0;
        }
        .kpi-subtitle {
            font-size: 11px;
            color: #999;
        }
    </style>
</head>
<body style="margin: 20px;">
    <h1>Dashboard Grid Test</h1>
    
    <h2>Test 1: Manual widgets (should work)</h2>
    <div class="dashboard-grid">
        <div class="widget" style="grid-column: span 2;">
            <div class="widget-title">Тест 1</div>
            <div class="kpi-value">45</div>
            <div class="kpi-subtitle">Всего звонков</div>
        </div>
        
        <div class="widget" style="grid-column: span 2;">
            <div class="widget-title">Тест 2</div>
            <div class="kpi-value">17.8%</div>
            <div class="kpi-subtitle">Конверсия</div>
        </div>
        
        <div class="widget" style="grid-column: span 4;">
            <div class="widget-title">Тест 3 (wide)</div>
            <div class="kpi-value">100</div>
        </div>
    </div>

    <h2>Test 2: JavaScript rendered (from API)</h2>
    <div id="test-container" class="dashboard-grid"></div>

    <h2>Console logs</h2>
    <div id="logs" style="background: #f5f5f5; padding: 15px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>

    <script>
        const logsDiv = document.getElementById('logs');
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            logsDiv.textContent += args.join(' ') + '\n';
        };

        async function testDashboard() {
            console.log('=== Starting dashboard test ===');
            
            try {
                // Test API
                console.log('Fetching dashboard list...');
                const listResponse = await fetch('/api/dashboards.php?action=list');
                const listData = await listResponse.json();
                console.log('Dashboard list:', listData);

                if (listData.success && listData.data.length > 0) {
                    const dashboardId = listData.data[0].dashboard_id;
                    console.log('Loading dashboard:', dashboardId);

                    const dashResponse = await fetch('/api/dashboards.php?action=get&id=' + dashboardId);
                    const dashData = await dashResponse.json();
                    console.log('Dashboard data:', dashData);

                    if (dashData.success && dashData.data.widgets) {
                        console.log('Widgets count:', dashData.data.widgets.length);

                        // Render first 3 widgets
                        const container = document.getElementById('test-container');
                        const widgets = dashData.data.widgets.slice(0, 3);

                        for (const widgetConfig of widgets) {
                            console.log('Creating widget:', widgetConfig.title);

                            const widget = document.createElement('div');
                            widget.className = 'widget widget-kpi';
                            widget.style.gridColumn = `span ${widgetConfig.size_width || 2}`;

                            const title = document.createElement('div');
                            title.className = 'widget-title';
                            title.textContent = widgetConfig.title;
                            widget.appendChild(title);

                            // Fetch data
                            const params = new URLSearchParams({
                                date_from: '2025-11-01',
                                date_to: '2025-12-07'
                            });

                            const dataResponse = await fetch(`/api/analytics/${widgetConfig.data_source}.php?${params}`);
                            const apiData = await dataResponse.json();
                            console.log(`Data for ${widgetConfig.title}:`, apiData);

                            if (apiData.success) {
                                const data = apiData.data;
                                const config = widgetConfig.config;

                                let value = null;
                                if (config.metric === 'total_calls') {
                                    value = Array.isArray(data) ? data[0]?.count : data.total_calls;
                                } else if (config.metric === 'success_rate') {
                                    value = Array.isArray(data) && data[3] ? data[3].conversion_from_previous : 0;
                                } else if (config.metric === 'deal_rate') {
                                    value = Array.isArray(data) && data[4] ? data[4].conversion_from_previous : 0;
                                }

                                let displayValue = value !== null && value !== undefined ? value : '-';
                                if (value !== null && value !== undefined) {
                                    if (config.format === 'percentage') {
                                        displayValue = parseFloat(value).toFixed(1) + '%';
                                    } else if (config.format === 'number') {
                                        displayValue = parseInt(value).toLocaleString();
                                    }
                                }

                                console.log(`Value for ${widgetConfig.title}: ${displayValue}`);

                                const valueDiv = document.createElement('div');
                                valueDiv.className = 'kpi-value';
                                valueDiv.textContent = displayValue;
                                widget.appendChild(valueDiv);

                                if (config.subtitle) {
                                    const subtitle = document.createElement('div');
                                    subtitle.className = 'kpi-subtitle';
                                    subtitle.textContent = config.subtitle;
                                    widget.appendChild(subtitle);
                                }
                            }

                            container.appendChild(widget);
                        }

                        console.log('=== Test complete ===');
                    }
                }
            } catch (error) {
                console.error('Test error:', error);
            }
        }

        testDashboard();
    </script>
</body>
</html>
