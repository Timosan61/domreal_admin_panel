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
            line-height: 1.5;
            height: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            align-items: center;
            flex-wrap: wrap;
        }

        #page-numbers {
            display: flex;
            gap: 0.25rem;
            align-items: center;
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

        /* Worker Status LED Animations */
        @keyframes pulse-green {
            0%, 100% { opacity: 1; box-shadow: 0 0 5px #4CAF50, 0 0 10px #4CAF50; }
            50% { opacity: 0.7; box-shadow: 0 0 2px #4CAF50; }
        }

        @keyframes pulse-red {
            0%, 100% { opacity: 1; box-shadow: 0 0 5px #f44336, 0 0 10px #f44336; }
            50% { opacity: 0.7; box-shadow: 0 0 2px #f44336; }
        }

        @keyframes pulse-gray {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        #worker-status-led.status-healthy {
            background: #4CAF50 !important;
            animation: pulse-green 2s infinite;
        }

        #worker-status-led.status-error {
            background: #f44336 !important;
            animation: pulse-red 1s infinite;
        }

        #worker-status-led.status-checking {
            background: #ccc !important;
            animation: pulse-gray 1.5s infinite;
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
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <div class="money-tracker-page">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="money-tracker-content">
            <!-- Header with Filters -->
            <div class="money-tracker-header">
                <!-- Batch Selector -->
                <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 0.375rem; border: 1px solid #dee2e6;">
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="font-weight: 600; margin: 0; white-space: nowrap;">📦 Мои загрузки:</label>
                        <select id="batch-selector" style="width: auto; max-width: 400px; padding: 0.5rem 0.75rem; border: 1px solid #ced4da; border-radius: 0.25rem; font-size: 0.875rem; background: white;">
                            <option value="">Все записи (без фильтра)</option>
                            <!-- Options loaded dynamically via JS -->
                        </select>
                        <button id="batch-details-btn" class="btn btn-secondary" style="white-space: nowrap;">
                            📊 Детали загрузки
                        </button>
                        <button class="btn btn-success" id="add-numbers-btn" style="white-space: nowrap;">➕ Добавить номера</button>
                        <button class="btn btn-success" id="add-file-btn" style="white-space: nowrap;">📎 Добавить файл</button>
                    </div>
                </div>

                <!-- Worker Status Indicator -->
                <div id="worker-status-panel" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.375rem; border: 1px solid #dee2e6; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; background: white;">
                    <!-- Status Indicator -->
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div id="worker-status-led" style="width: 12px; height: 12px; border-radius: 50%; background: #ccc;"></div>
                        <span style="font-weight: 600; font-size: 0.875rem;" id="worker-status-text">Проверка worker...</span>
                    </div>

                    <!-- Queue Info -->
                    <div id="worker-queue-info" style="display: none; font-size: 0.875rem; color: #666;">
                        Очередь: <strong id="worker-queue-count">0</strong> записей
                    </div>

                    <!-- Processing Speed -->
                    <div id="worker-speed-info" style="display: none; font-size: 0.875rem; color: #666;">
                        Скорость: <strong id="worker-speed-value">0</strong> зап/мин
                    </div>

                    <!-- Active Batches -->
                    <div id="worker-batches-info" style="display: none; font-size: 0.875rem; color: #666;">
                        Активных загрузок: <strong id="worker-batches-count">0</strong>
                    </div>

                    <!-- Error Display -->
                    <div id="worker-error-container" style="display: none; flex: 1 1 100%; margin-top: 0.5rem; padding: 0.75rem; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 0.25rem;">
                        <div style="font-weight: 600; color: #856404; margin-bottom: 0.5rem;">⚠️ Проблемы с worker:</div>
                        <ul id="worker-error-list" style="margin: 0; padding-left: 1.5rem; color: #856404; font-size: 0.875rem;">
                            <!-- Errors populated by JS -->
                        </ul>
                    </div>

                    <!-- Last Update -->
                    <div style="margin-left: auto; font-size: 0.75rem; color: #999;" id="worker-last-update">
                        Обновлено: никогда
                    </div>
                </div>

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

                    <div class="filter-group">
                        <label for="webhook_source_filter">Источник данных:</label>
                        <select id="webhook_source_filter">
                            <option value="">Все источники</option>
                            <option value="gck">🟦 GCK Webhook</option>
                            <option value="calls">📞 Beeline Звонки</option>
                        </select>
                    </div>

                    <div class="filter-group" style="grid-column: 1 / -1;">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
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

                    <div class="filter-actions" style="grid-column: 1 / -1; display: flex; gap: 0.5rem; align-items: center;">
                        <button class="btn btn-primary" id="apply-filters">Применить</button>
                        <button class="btn btn-secondary" id="reset-filters">Сбросить</button>
                        <button class="btn btn-success" id="export-filtered-btn">📊 Экспортировать XLSX</button>
                    </div>
                </div>
            </div>

            <!-- Stats & Table Body -->
            <div class="money-tracker-body">
                <!-- Bulk Actions Panel (hidden by default) -->
                <div id="bulk-actions-panel" style="display: none; background: #e3f2fd; padding: 0.75rem 1rem; border-radius: 0.25rem; margin-bottom: 1rem; border-left: 4px solid #2196F3;">
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <span style="font-weight: 600; color: #1976D2;">
                            <span id="selected-count">0</span> выбрано
                        </span>
                        <button class="btn btn-primary" id="export-selected-btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">📊 Экспорт выбранных</button>
                        <button class="btn" id="delete-selected-btn" style="background: #f44336; color: white; padding: 0.4rem 0.8rem; font-size: 0.8rem;">🗑️ Удалить выбранные</button>
                        <button class="btn btn-secondary" id="deselect-all-btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">✖ Снять выделение</button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-cards" style="display: none;">
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
                        <div style="color: #666; font-size: 0.875rem;">
                            Показано: <span id="showing-count">0</span> из <span id="total-count">0</span>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="enrichment-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">
                                        <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Выбрать все">
                                    </th>
                                    <th class="sortable" data-sort="id">ID</th>
                                    <th class="sortable" data-sort="client_phone">Телефон</th>
                                    <th style="width: 150px;">Источник</th>
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

    <!-- Import Modal -->
    <div class="modal" id="import-modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">📥 Добавить номера телефонов</h2>
                <button class="modal-close" id="import-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.5rem;">
                    <label for="import-batch-name" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        📦 Название загрузки:
                    </label>
                    <input
                        type="text"
                        id="import-batch-name"
                        placeholder="Клиенты Москва январь 2025"
                        style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ced4da; border-radius: 0.25rem; font-size: 0.875rem;"
                        required
                    />
                    <small style="color: #6c757d; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                        Введите понятное название для этой загрузки, чтобы позже легко найти её в списке
                    </small>
                </div>
                <p style="color: #666; margin-bottom: 1rem; font-size: 0.875rem;">
                    Вставьте список телефонных номеров (по одному на строку или через запятую):
                </p>
                <textarea
                    id="import-phones-textarea"
                    placeholder="+79001234567&#10;+79001234568&#10;+79001234569"
                    style="width: 100%; min-height: 300px; padding: 0.75rem; border: 1px solid #ddd; border-radius: 0.25rem; font-family: monospace; font-size: 0.875rem; resize: vertical;"
                ></textarea>
                <div style="margin-top: 1rem; padding: 0.75rem; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 0.25rem; font-size: 0.875rem;">
                    <strong>Форматы номеров:</strong> +79001234567, 89001234567, 9001234567
                </div>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button class="btn btn-secondary" id="import-cancel-btn">Отмена</button>
                    <button class="btn btn-success" id="import-submit-btn">➕ Добавить в базу</button>
                </div>
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div class="modal" id="file-upload-modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 class="modal-title">📎 Загрузить файл с номерами</h2>
                <button class="modal-close" id="file-upload-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Batch Name -->
                <div style="margin-bottom: 1rem;">
                    <label for="file-batch-name" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Название загрузки:
                    </label>
                    <input
                        type="text"
                        id="file-batch-name"
                        placeholder="Клиенты Москва январь 2025"
                        style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem; font-size: 0.875rem;"
                    >
                </div>

                <!-- File Input -->
                <div style="margin-bottom: 1rem;">
                    <label for="file-input" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Выберите файл:
                    </label>
                    <input
                        type="file"
                        id="file-input"
                        accept=".xlsx,.xls,.csv,.txt"
                        style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem; font-size: 0.875rem; background: white;"
                    >
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #666;">
                        Поддерживаемые форматы: Excel (.xlsx, .xls), CSV (.csv), текстовый файл (.txt)
                    </div>
                </div>

                <!-- Column Selection (hidden initially) -->
                <div id="column-selection-container" style="display: none; margin-bottom: 1rem;">
                    <label for="phone-column-select" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Выберите столбец с телефонами:
                    </label>
                    <select
                        id="phone-column-select"
                        style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem; font-size: 0.875rem; background: white;"
                    >
                        <!-- Options will be populated by JS -->
                    </select>
                </div>

                <!-- Phone Preview -->
                <div id="phone-preview-container" style="display: none; margin-bottom: 1rem;">
                    <div style="margin-bottom: 0.5rem; font-weight: 600;">
                        Найдено номеров: <span id="phone-count" style="color: #28a745;">0</span>
                    </div>
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.25rem; padding: 0.75rem; max-height: 200px; overflow-y: auto;">
                        <div id="phone-preview-list" style="font-family: monospace; font-size: 0.875rem; line-height: 1.8;">
                            <!-- Phone numbers will be populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div style="margin-top: 1rem; padding: 0.75rem; background: #fff3cd; border-radius: 0.25rem;">
                    <strong>💡 Форматы номеров:</strong><br>
                    Система автоматически распознает номера в форматах:<br>
                    +79001234567, 89001234567, 79001234567, 9001234567
                </div>

                <!-- Actions -->
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button class="btn btn-secondary" id="file-upload-cancel-btn">Отмена</button>
                    <button class="btn btn-success" id="file-upload-submit-btn" disabled>➕ Добавить в базу</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal" id="progress-modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 class="modal-title">⏳ Обработка номеров</h2>
                <button class="modal-close" id="progress-modal-close" style="display: none;">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        <span style="color: #666;">Прогресс:</span>
                        <span style="font-weight: 600; color: #2196F3;">
                            <span id="progress-processed">0</span> / <span id="progress-total">0</span>
                        </span>
                    </div>
                    <div style="width: 100%; height: 30px; background: #e0e0e0; border-radius: 15px; overflow: hidden;">
                        <div
                            id="progress-bar"
                            style="width: 0%; height: 100%; background: linear-gradient(90deg, #2196F3, #4CAF50); transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;"
                        >
                            0%
                        </div>
                    </div>
                </div>

                <div style="background: #f5f5f5; border-radius: 0.25rem; padding: 1rem; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.8rem;">
                    <div id="progress-log">
                        <div style="color: #666;">Ожидание запуска...</div>
                    </div>
                </div>

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                    <button class="btn btn-secondary" id="progress-close-btn" style="display: none;">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Details Modal -->
    <div class="modal" id="batch-details-modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2 class="modal-title">📊 Детали загрузки</h2>
                <button class="modal-close" id="batch-details-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Batch Info Header -->
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 0.375rem; margin-bottom: 1.5rem;">
                    <h3 id="batch-details-name" style="margin: 0 0 1rem 0; font-size: 1.125rem; color: #212529;">-</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.875rem;">
                        <div>
                            <strong style="color: #6c757d;">Дата создания:</strong><br>
                            <span id="batch-details-created">-</span>
                        </div>
                        <div>
                            <strong style="color: #6c757d;">Автор:</strong><br>
                            <span id="batch-details-author">-</span>
                        </div>
                        <div>
                            <strong style="color: #6c757d;">Статус:</strong><br>
                            <span id="batch-details-status-badge" class="badge">-</span>
                        </div>
                        <div>
                            <strong style="color: #6c757d;">Завершено:</strong><br>
                            <span id="batch-details-completed">-</span>
                        </div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 1rem 0; font-size: 1rem; color: #495057;">📈 Прогресс обработки</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: #e7f3ff; border-radius: 0.375rem;">
                            <div style="font-size: 1.75rem; font-weight: 700; color: #0066cc;" id="batch-details-total">-</div>
                            <div style="font-size: 0.75rem; color: #495057; margin-top: 0.25rem;">Всего записей</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #d4edda; border-radius: 0.375rem;">
                            <div style="font-size: 1.75rem; font-weight: 700; color: #28a745;" id="batch-details-processed">-</div>
                            <div style="font-size: 0.75rem; color: #495057; margin-top: 0.25rem;">Обработано</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #d1ecf1; border-radius: 0.375rem;">
                            <div style="font-size: 1.75rem; font-weight: 700; color: #17a2b8;" id="batch-details-completed-count">-</div>
                            <div style="font-size: 0.75rem; color: #495057; margin-top: 0.25rem;">Успешно</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #f8d7da; border-radius: 0.375rem;">
                            <div style="font-size: 1.75rem; font-weight: 700; color: #dc3545;" id="batch-details-error">-</div>
                            <div style="font-size: 0.75rem; color: #495057; margin-top: 0.25rem;">Ошибки</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #fff3cd; border-radius: 0.375rem;">
                            <div style="font-size: 1.75rem; font-weight: 700; color: #ffc107;" id="batch-details-pending">-</div>
                            <div style="font-size: 0.75rem; color: #495057; margin-top: 0.25rem;">Ожидают</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #e2e3e5; border-radius: 0.375rem;">
                            <div style="font-size: 1.75rem; font-weight: 700; color: #6c757d;" id="batch-details-inn">-</div>
                            <div style="font-size: 0.75rem; color: #495057; margin-top: 0.25rem;">ИНН найдено</div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div style="background: #e9ecef; border-radius: 0.5rem; overflow: hidden; height: 2rem; position: relative;">
                        <div id="batch-details-progress-bar" style="background: linear-gradient(90deg, #28a745, #20c997); height: 100%; width: 0%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                            0%
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #dee2e6;">
                    <button id="batch-details-export-btn" class="btn btn-success">
                        📥 Экспортировать XLSX
                    </button>
                    <button class="btn btn-secondary" id="batch-details-close-btn">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Scripts -->
    <!-- SheetJS Library for Excel file parsing -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/fetch_retry.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/money_tracker.js?v=<?php echo time(); ?>"></script>
</body>
</html>
