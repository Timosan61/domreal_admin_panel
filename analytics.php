<?php
session_start();
require_once 'auth/session.php';
checkAuth();

$user_full_name = $_SESSION['full_name'] ?? 'Пользователь';
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - Domreal Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        .analytics-page {
            display: flex;
            height: 100vh;
            overflow: hidden;
            margin-left: 250px;
        }

        .analytics-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f8f9fa;
            width: 100%;
        }

        .analytics-header {
            background: white;
            padding: 15px 25px;
            border-bottom: 1px solid #e0e0e0;
            flex-shrink: 0;
        }

        .analytics-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .analytics-header h1 {
            margin: 0;
            font-size: 22px;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-settings-btn {
            padding: 8px 16px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: #666;
        }

        .dashboard-settings-btn:hover {
            background: #e0e0e0;
            border-color: #ccc;
        }

        .dashboard-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .dashboard-selector select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 250px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 150px 150px 1fr;
            gap: 10px;
            align-items: end;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
            display: block;
        }

        .filter-group input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            width: 100%;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 6px 16px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2196F3;
            color: white;
        }

        .btn-primary:hover {
            background: #1976D2;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .analytics-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px 20px;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 15px;
            width: 100%;
        }

        /* Виджеты */
        .widget {
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 15px;
            min-height: 100px;
        }

        .widget-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            text-align: center;
        }

        /* KPI Card */
        .widget-kpi {
            text-align: center;
        }

        .widget-kpi .kpi-value {
            font-size: 32px;
            font-weight: 600;
            color: #2196F3;
            margin: 10px 0;
        }

        .widget-kpi .kpi-subtitle {
            font-size: 11px;
            color: #999;
        }

        /* Chart Widget */
        .widget-chart {
            position: relative;
        }

        .widget-chart canvas {
            max-height: 300px;
        }

        /* Table Widget */
        .widget-table {
            overflow-x: auto;
        }

        .widget-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .widget-table th {
            background: #f5f5f5;
            padding: 8px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }

        .widget-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .widget-table tr:hover {
            background: #f9f9f9;
        }

        /* Loading */
        .widget-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            color: #999;
        }

        /* Error */
        .widget-error {
            color: #f44336;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="analytics-page">
        <div class="analytics-content">
            <!-- Header -->
            <div class="analytics-header">
                <div class="analytics-header-top">
                    <h1>📊 Аналитика</h1>
                    <a href="/dashboard_settings.php" class="dashboard-settings-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6m5.2-15.8l-4.2 4.2m0 6l4.2 4.2M23 12h-6m-6 0H1m15.8 5.2l-4.2-4.2m0-6l-4.2-4.2"></path>
                        </svg>
                        Настройка дашбордов
                    </a>
                </div>

                <!-- Dashboard Selector -->
                <div class="dashboard-selector">
                    <label>Дашборд:</label>
                    <select id="dashboard-select">
                        <option value="">Загрузка...</option>
                    </select>
                </div>

                <!-- Filters -->
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Дата от:</label>
                        <input type="date" id="date-from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Дата до:</label>
                        <input type="date" id="date-to" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary" id="apply-filters">Применить</button>
                        <button class="btn btn-secondary" id="reset-filters">Сбросить</button>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="analytics-body">
                <div class="dashboard-grid" id="dashboard-container">
                    <div class="widget-loading" style="grid-column: 1 / -1;">
                        <div>Загрузка дашборда...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/analytics_dashboard.js"></script>
</body>
</html>
