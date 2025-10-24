<?php
session_start();
require_once 'auth/session.php';

// ВАЖНО: Доступ только для администраторов!
checkAuth($require_admin = true);

$user_full_name = $_SESSION['full_name'] ?? 'Администратор';
$user_role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰 Money Tracker - Domreal Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
        }

        .money-tracker-page {
            display: flex;
            height: 100vh;
            overflow: hidden;
            margin-left: 15.625rem; /* 250px */
            transition: margin-left 0.3s ease;
        }

        body.sidebar-collapsed .money-tracker-page {
            margin-left: 4.375rem; /* 70px */
        }

        .money-tracker-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f8f9fa;
            width: 100%;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 15.625rem;
            z-index: 1000;
            background: white;
            box-shadow: 0.125rem 0 0.5rem rgba(0,0,0,0.1);
        }

        .money-tracker-header {
            background: white;
            padding: 1rem 1.5rem;
            border-bottom: 0.0625rem solid #e0e0e0;
            flex-shrink: 0;
        }

        .money-tracker-header h1 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 0.0625rem solid #ddd;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.25rem;
            font-size: 0.875rem;
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

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .money-tracker-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.1);
        }

        .stat-card-title {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
        }

        .stat-card-subtitle {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.25rem;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 1rem 1.5rem;
            border-bottom: 0.0625rem solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #333;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        thead {
            background: #f5f5f5;
            border-bottom: 0.125rem solid #e0e0e0;
        }

        th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }

        th.sortable {
            cursor: pointer;
            user-select: none;
        }

        th.sortable:hover {
            background: #e8e8e8;
        }

        th.sortable::after {
            content: ' ⇅';
            color: #999;
            font-size: 0.75rem;
        }

        th.sortable.asc::after {
            content: ' ↑';
            color: #2196F3;
        }

        th.sortable.desc::after {
            content: ' ↓';
            color: #2196F3;
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 0.0625rem solid #f0f0f0;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }

        .text-truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .text-muted {
            color: #999;
            font-size: 0.8rem;
        }

        .clickable {
            color: #2196F3;
            cursor: pointer;
            text-decoration: underline;
        }

        .clickable:hover {
            color: #1976D2;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 0.0625rem solid #e0e0e0;
        }

        .pagination-info {
            color: #666;
            font-size: 0.875rem;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .pagination-btn {
            padding: 0.375rem 0.75rem;
            border: 0.0625rem solid #ddd;
            background: white;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #f5f5f5;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: #2196F3;
            color: white;
            border-color: #2196F3;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal.active {
            display: block;
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 1000px;
            box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.2);
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 0.0625rem solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }

        .modal-close {
            font-size: 1.5rem;
            font-weight: 300;
            color: #999;
            cursor: pointer;
            border: none;
            background: none;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #333;
        }

        .detail-value.json {
            font-family: monospace;
            background: #f5f5f5;
            padding: 0.5rem;
            border-radius: 0.25rem;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 3rem;
            height: 3rem;
            border: 0.25rem solid #f3f3f3;
            border-top: 0.25rem solid #2196F3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1200px) {
            .filters-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .filter-group[style*="span 3"],
            .filter-actions[style*="span 2"] {
                grid-column: span 2 !important;
            }
        }

        @media (max-width: 1024px) {
            .filters-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .filter-group[style*="span 3"] {
                grid-column: span 4 !important;
            }

            .filter-actions[style*="span 2"] {
                grid-column: span 4 !important;
            }
        }

        @media (max-width: 768px) {
            .money-tracker-page {
                margin-left: 0;
            }

            body.sidebar-collapsed .money-tracker-page {
                margin-left: 0;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-group[style*="span"],
            .filter-actions[style*="span"] {
                grid-column: 1 !important;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="money-tracker-page">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="money-tracker-content">
            <!-- Header with Filters -->
            <div class="money-tracker-header">
                <h1>💰 Money Tracker</h1>
                <p style="color: #666; font-size: 0.875rem; margin: 0 0 1rem 0;">
                    Система обогащения данных клиентов через Userbox API, RusProfile и GigaChat
                </p>

                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="date_from">Дата создания с:</label>
                        <input type="date" id="date_from" value="">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">Дата создания по:</label>
                        <input type="date" id="date_to" value="">
                    </div>

                    <div class="filter-group">
                        <label for="enriched_date_from">Дата обогащения с:</label>
                        <input type="date" id="enriched_date_from" value="">
                    </div>

                    <div class="filter-group">
                        <label for="enriched_date_to">Дата обогащения по:</label>
                        <input type="date" id="enriched_date_to" value="">
                    </div>

                    <div class="filter-group">
                        <label for="status_filter">Статус обогащения:</label>
                        <select id="status_filter">
                            <option value="">Все статусы</option>
                            <option value="completed">Завершено</option>
                            <option value="in_progress">В процессе</option>
                            <option value="error">Ошибка</option>
                            <option value="pending">Ожидание</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="inn_filter">Наличие ИНН:</label>
                        <select id="inn_filter">
                            <option value="">Все записи</option>
                            <option value="yes">Только с ИНН</option>
                            <option value="no">Без ИНН</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="phone_search">Поиск по телефону:</label>
                        <input type="text" id="phone_search" placeholder="+79001234567">
                    </div>

                    <div class="filter-group" style="grid-column: span 3;">
                        <label>Уровень платежеспособности:</label>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.25rem;">
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                <input type="checkbox" class="solvency-level-checkbox" value="green" style="cursor: pointer;">
                                <span>🟢 Низкая (до 10 млн)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                <input type="checkbox" class="solvency-level-checkbox" value="blue" style="cursor: pointer;">
                                <span>🔵 Средняя (до 100 млн)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                <input type="checkbox" class="solvency-level-checkbox" value="yellow" style="cursor: pointer;">
                                <span>🟡 Высокая (до 500 млн)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                <input type="checkbox" class="solvency-level-checkbox" value="red" style="cursor: pointer;">
                                <span>🔴 Очень высокая (до 2 млрд)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                <input type="checkbox" class="solvency-level-checkbox" value="purple" style="cursor: pointer;">
                                <span>🟣 Премиальная (свыше 2 млрд)</span>
                            </label>
                        </div>
                    </div>

                    <div class="filter-actions" style="grid-column: span 2;">
                        <button class="btn btn-primary" id="apply-filters">Применить</button>
                        <button class="btn btn-secondary" id="reset-filters">Сбросить</button>
                    </div>
                </div>
            </div>

            <!-- Stats & Table Body -->
            <div class="money-tracker-body">
                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-card-title">Всего записей</div>
                        <div class="stat-card-value" id="stat-total">-</div>
                        <div class="stat-card-subtitle">в базе данных</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-title">ИНН найдено</div>
                        <div class="stat-card-value" id="stat-inn">-</div>
                        <div class="stat-card-subtitle">процент покрытия</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #4CAF50;">
                        <div class="stat-card-title">🟢 Низкая (до 10 млн)</div>
                        <div class="stat-card-value" id="stat-solvency-green">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-green-pct">-</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #2196F3;">
                        <div class="stat-card-title">🔵 Средняя (до 100 млн)</div>
                        <div class="stat-card-value" id="stat-solvency-blue">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-blue-pct">-</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #FFC107;">
                        <div class="stat-card-title">🟡 Высокая (до 500 млн)</div>
                        <div class="stat-card-value" id="stat-solvency-yellow">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-yellow-pct">-</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #F44336;">
                        <div class="stat-card-title">🔴 Очень высокая (до 2 млрд)</div>
                        <div class="stat-card-value" id="stat-solvency-red">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-red-pct">-</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #9C27B0;">
                        <div class="stat-card-title">🟣 Премиальная (свыше 2 млрд)</div>
                        <div class="stat-card-value" id="stat-solvency-purple">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-purple-pct">-</div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">Данные обогащения клиентов</div>
                        <div style="color: #666; font-size: 0.875rem;">
                            Показано: <span id="showing-count">0</span>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="enrichment-table">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="id">ID</th>
                                    <th class="sortable" data-sort="client_phone">Телефон</th>
                                    <th class="sortable" data-sort="inn">ИНН</th>
                                    <th style="width: 80px;">Компаний</th>
                                    <th style="width: 150px;">Выручка (₽)</th>
                                    <th style="width: 150px;">Прибыль (₽)</th>
                                    <th class="sortable" data-sort="solvency_level">Платежеспособность</th>
                                    <th class="sortable" data-sort="enrichment_status">Статус</th>
                                    <th class="sortable" data-sort="created_at">Создано</th>
                                    <th class="sortable" data-sort="updated_at">Обновлено</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="enrichment-tbody">
                                <!-- Заполняется через JS -->
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination">
                        <div class="pagination-info">
                            Страница <span id="current-page">1</span> из <span id="total-pages">1</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="pagination-btn" id="first-page">Первая</button>
                            <button class="pagination-btn" id="prev-page">← Назад</button>
                            <span id="page-numbers"></span>
                            <button class="pagination-btn" id="next-page">Вперед →</button>
                            <button class="pagination-btn" id="last-page">Последняя</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal" id="detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Детали обогащения</h2>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modal-body">
                <!-- Заполняется через JS -->
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/fetch_retry.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/money_tracker.js?v=<?php echo time(); ?>"></script>
</body>
</html>
