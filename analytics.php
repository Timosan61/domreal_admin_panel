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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=0.5, maximum-scale=3.0">
    <title>Аналитика - Domreal Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Better responsive support for browser zoom */
        * {
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
        }
        .analytics-page {
            display: flex;
            height: 100vh;
            overflow: hidden;
            margin-left: 15.625rem; /* 250px в rem для масштабирования */
        }

        .analytics-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f8f9fa;
            width: 100%;
            box-sizing: border-box;
        }

        /* Sidebar fixed position */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 15.625rem; /* 250px */
            z-index: 1000;
            background: white;
            box-shadow: 0.125rem 0 0.5rem rgba(0,0,0,0.1);
            transition: width 0.3s ease;
        }

        /* Collapsed sidebar */
        .sidebar.collapsed {
            width: 4.375rem; /* 70px */
        }

        body.sidebar-collapsed .analytics-page {
            margin-left: 4.375rem; /* 70px */
        }

        body.sidebar-collapsed .analytics-content {
            transition: margin-left 0.3s ease;
        }

        /* Hide sidebar header logo */
        .sidebar-header {
            display: none;
        }

        .sidebar-logo {
            display: none;
        }

        /* Ensure toggle button is visible */
        .sidebar-toggle {
            padding: 0.9375rem; /* 15px */
            border-bottom: 0.0625rem solid #e0e0e0;
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 0.9375rem; /* 15px */
            left: 0.9375rem; /* 15px */
            z-index: 999;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 0.5rem; /* 8px */
            padding: 0.625rem 0.75rem; /* 10px 12px */
            cursor: pointer;
            box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.15);
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 48rem) { /* 768px */
            .analytics-page {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .mobile-menu-btn {
                display: block;
            }
        }

        .analytics-header {
            background: white;
            padding: 0.9375rem 1.5625rem; /* 15px 25px */
            border-bottom: 0.0625rem solid #e0e0e0;
            flex-shrink: 0;
        }

        .analytics-header h1 {
            margin: 0 0 0.9375rem 0; /* 15px */
            font-size: 1.375rem; /* 22px */
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem; /* 8px */
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 9.375rem 9.375rem 12.5rem 12.5rem auto; /* 150px 150px 200px 200px auto */
            gap: 0.625rem; /* 10px */
            align-items: end;
        }

        @media (max-width: 75rem) { /* 1200px */
            .filters-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 48rem) { /* 768px */
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.75rem; /* 12px */
            color: #666;
            margin-bottom: 0.25rem; /* 4px */
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.375rem 0.625rem; /* 6px 10px */
            border: 0.0625rem solid #ddd;
            border-radius: 0.25rem; /* 4px */
            font-size: 0.8125rem; /* 13px */
        }

        .filter-actions {
            display: flex;
            gap: 0.625rem; /* 10px */
        }

        .btn {
            padding: 0.375rem 1rem; /* 6px 16px */
            border: none;
            border-radius: 0.25rem; /* 4px */
            font-size: 0.8125rem; /* 13px */
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

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .analytics-body {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.9375rem 1.25rem; /* 15px 20px */
            width: 100%;
            box-sizing: border-box;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.9375rem; /* 15px */
            width: 100%;
            box-sizing: border-box;
        }

        .dashboard-grid .chart-container.full-width {
            grid-column: 1 / -1;
        }

        @media (max-width: 64rem) { /* 1024px */
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* KPI Cards */
        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(11.25rem, 1fr)); /* 180px */
            gap: 0.75rem; /* 12px */
            margin-bottom: 0.9375rem; /* 15px */
            width: 100%;
        }

        .kpi-card {
            background: white;
            padding: 0.9375rem; /* 15px */
            border-radius: 0.375rem; /* 6px */
            box-shadow: 0 0.0625rem 0.1875rem rgba(0,0,0,0.1);
        }

        .kpi-card-title {
            font-size: 0.75rem; /* 12px */
            color: #666;
            margin-bottom: 0.5rem; /* 8px */
        }

        .kpi-card-value {
            font-size: 1.75rem; /* 28px */
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem; /* 4px */
        }

        .kpi-card-subtitle {
            font-size: 0.6875rem; /* 11px */
            color: #999;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            padding: 0.9375rem; /* 15px */
            border-radius: 0.375rem; /* 6px */
            box-shadow: 0 0.0625rem 0.1875rem rgba(0,0,0,0.1);
            margin-bottom: 0.9375rem; /* 15px */
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .chart-title {
            font-size: 1rem; /* 16px */
            font-weight: 600;
            color: #333;
            margin-bottom: 0.75rem; /* 12px */
        }

        .chart-canvas {
            width: 100%;
            height: 25rem; /* 400px */
            min-height: 18.75rem; /* 300px */
        }

        .chart-canvas.small {
            height: 18.75rem; /* 300px */
            min-height: 15rem; /* 240px */
        }

        .chart-canvas.large {
            height: 31.25rem; /* 500px */
            min-height: 25rem; /* 400px */
        }


        /* Loading Spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 3.125rem; /* 50px */
            height: 3.125rem; /* 50px */
            border: 0.25rem solid #f3f3f3;
            border-top: 0.25rem solid #2196F3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Multi-select */
        .multi-select-wrapper {
            position: relative;
        }

        .multi-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 100%;
            width: max-content;
            max-width: 31.25rem; /* 500px */
            background: white;
            border: 0.0625rem solid #ddd;
            border-radius: 0.25rem; /* 4px */
            max-height: 25rem; /* 400px */
            display: none;
            flex-direction: column;
            z-index: 100;
            margin-top: 0.125rem; /* 2px */
            box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1);
        }

        .multi-select-dropdown.active {
            display: flex;
        }

        .multi-select-header {
            padding: 0.625rem; /* 10px */
            border-bottom: 0.0625rem solid #e5e5e5;
            display: flex;
            flex-direction: column;
            gap: 0.375rem; /* 6px */
            flex-shrink: 0;
        }

        .multi-select-search {
            flex: 1;
            padding: 0.375rem 0.625rem; /* 6px 10px */
            border: 0.0625rem solid #D0D0D0;
            border-radius: 0.1875rem; /* 3px */
            font-size: 0.8125rem; /* 13px */
            outline: none;
            box-sizing: border-box;
        }

        .multi-select-search:focus {
            border-color: #2196F3;
        }

        .multi-select-header-buttons {
            display: flex;
            gap: 0.375rem; /* 6px */
        }

        .multi-select-btn {
            padding: 0.3125rem 0.625rem; /* 5px 10px */
            background: #f5f5f5;
            border: none;
            border-radius: 0.1875rem; /* 3px */
            font-size: 0.75rem; /* 12px */
            color: #2196F3;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .multi-select-btn:hover {
            background: #E3F2FD;
        }

        .multi-select-btn:last-child {
            color: #666;
        }

        .multi-select-btn:last-child:hover {
            background: #FFEBEE;
            color: #f44336;
        }

        .multi-select-options {
            overflow-y: auto;
            max-height: 21.875rem; /* 350px */
            flex: 1;
        }

        .multi-select-option {
            padding: 0.5rem 0.75rem; /* 8px 12px */
            cursor: pointer;
            display: flex;
            align-items: flex-start;
        }

        .multi-select-option:hover {
            background: #f5f5f5;
        }

        .multi-select-option input {
            margin-right: 0.5rem; /* 8px */
            margin-top: 0.25rem; /* 4px - выравнивание с текстом */
            flex-shrink: 0;
        }

        .multi-select-option label {
            flex: 1;
            white-space: normal;
            word-break: break-word;
            line-height: 1.5;
        }

        .multi-select-display {
            cursor: pointer;
            padding: 0.375rem 0.625rem; /* 6px 10px */
            border: 0.0625rem solid #ddd;
            border-radius: 0.25rem; /* 4px */
            background: white;
            min-height: 2rem; /* 32px */
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8125rem; /* 13px */
        }

        .multi-select-display::after {
            content: '▼';
            font-size: 0.5625rem; /* 9px */
            color: #666;
        }
    </style>
</head>
<body>
    <div class="analytics-page">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="analytics-content">
            <!-- Header with Filters -->
            <div class="analytics-header">
                <h1>📊 Аналитика</h1>

                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="date_from">Дата с:</label>
                        <input type="date" id="date_from" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">Дата по:</label>
                        <input type="date" id="date_to" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Отделы:</label>
                        <div class="multi-select-wrapper">
                            <div class="multi-select-display" id="departments-display">
                                <span>Все отделы</span>
                            </div>
                            <div class="multi-select-dropdown" id="departments-dropdown">
                                <div class="multi-select-header">
                                    <input type="text" class="multi-select-search" id="departments-search" placeholder="Поиск">
                                    <div class="multi-select-header-buttons">
                                        <button type="button" class="multi-select-btn" id="departments-select-all">Выбрать все</button>
                                        <button type="button" class="multi-select-btn" id="departments-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multi-select-options" id="departments-options">
                                    <!-- Будет заполнено через JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Менеджеры:</label>
                        <div class="multi-select-wrapper">
                            <div class="multi-select-display" id="managers-display">
                                <span>Все менеджеры</span>
                            </div>
                            <div class="multi-select-dropdown" id="managers-dropdown">
                                <div class="multi-select-header">
                                    <input type="text" class="multi-select-search" id="managers-search" placeholder="Поиск">
                                    <div class="multi-select-header-buttons">
                                        <button type="button" class="multi-select-btn" id="managers-select-all">Выбрать все</button>
                                        <button type="button" class="multi-select-btn" id="managers-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multi-select-options" id="managers-options">
                                    <!-- Будет заполнено через JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="filter-actions">
                            <button class="btn btn-primary" id="apply-filters">Применить</button>
                            <button class="btn btn-secondary" id="reset-filters">Сбросить</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Body -->
            <div class="analytics-body">
                <!-- KPI Cards -->
                <div class="kpi-cards">
                    <div class="kpi-card">
                        <div class="kpi-card-title">Всего звонков</div>
                        <div class="kpi-card-value" id="kpi-total-calls">-</div>
                        <div class="kpi-card-subtitle">за выбранный период</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card-title">Проанализировано</div>
                        <div class="kpi-card-value" id="kpi-analyzed-calls">-</div>
                        <div class="kpi-card-subtitle">звонков с анализом</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card-title">Показ назначен</div>
                        <div class="kpi-card-value" id="kpi-successful-calls">-</div>
                        <div class="kpi-card-subtitle">назначено показов</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card-title">Показ состоялся</div>
                        <div class="kpi-card-value" id="kpi-conversion-rate">-</div>
                        <div class="kpi-card-subtitle">завершенных показов</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card-title">Первые звонки</div>
                        <div class="kpi-card-value" id="kpi-first-calls">-</div>
                        <div class="kpi-card-subtitle">новых клиентов</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card-title">Средний балл скрипта</div>
                        <div class="kpi-card-value" id="kpi-script-score">-</div>
                        <div class="kpi-card-subtitle">выполнение скрипта</div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Departments Chart -->
                    <div class="chart-container">
                        <div class="chart-title">Результативность отделов (Топ-10)</div>
                        <div id="departments-chart" class="chart-canvas large"></div>
                    </div>

                    <!-- Managers Chart -->
                    <div class="chart-container">
                        <div class="chart-title">Топ-10 менеджеров</div>
                        <div id="managers-chart" class="chart-canvas large"></div>
                    </div>

                    <!-- Funnel Chart -->
                    <div class="chart-container">
                        <div class="chart-title">Воронка конверсии</div>
                        <div id="funnel-chart" class="chart-canvas"></div>
                    </div>

                    <!-- Dynamics Chart -->
                    <div class="chart-container">
                        <div class="chart-title">Динамика звонков по дням</div>
                        <div id="dynamics-chart" class="chart-canvas"></div>
                    </div>

                    <!-- Script Quality Chart -->
                    <div class="chart-container">
                        <div class="chart-title">Качество выполнения скрипта первого звонка</div>
                        <div id="script-quality-chart" class="chart-canvas"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/js/analytics.js"></script>
</body>
</html>
