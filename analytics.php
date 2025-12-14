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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">

    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                    <div class="widget-loading grid-full-width">
                        <div>Загрузка дашборда...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/analytics_dashboard.js"></script>
</body>
</html>
