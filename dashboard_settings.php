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
    <title>Настройка дашбордов - Domreal Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .dashboard-settings-page {
            display: flex;
            height: 100vh;
            overflow: hidden;
            margin-left: 250px;
        }

        .dashboard-settings-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f8f9fa;
        }

        .settings-header {
            background: white;
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
            flex-shrink: 0;
        }

        .settings-header h1 {
            margin: 0 0 10px 0;
            font-size: 22px;
            color: #333;
            font-weight: 600;
        }

        .settings-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 16px;
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

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .settings-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 25px;
        }

        /* Dashboard List */
        .dashboard-list {
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .dashboard-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dashboard-item:last-child {
            border-bottom: none;
        }

        .dashboard-info {
            flex: 1;
        }

        .dashboard-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .dashboard-meta {
            font-size: 12px;
            color: #999;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 8px;
        }

        .badge-default {
            background: #4CAF50;
            color: white;
        }

        .dashboard-actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 4px 10px;
            font-size: 12px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 1;
        }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Widget Editor */
        .widget-list {
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
        }

        .widget-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
        }

        .widget-item:hover {
            background: #f9f9f9;
        }

        .widget-item:last-child {
            border-bottom: none;
        }

        .widget-item-info {
            flex: 1;
        }

        .widget-title {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .widget-details {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .widget-item-actions {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            background: #f5f5f5;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            color: #666;
        }

        .btn-icon:hover {
            background: #e0e0e0;
        }

        /* Widget Config Form */
        .widget-config-section {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
        }

        .widget-config-section h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #555;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Error */
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="dashboard-settings-page">
        <div class="dashboard-settings-content">
            <!-- Header -->
            <div class="settings-header">
                <h1>⚙️ Настройка дашбордов</h1>
                <p style="margin: 0; color: #666; font-size: 13px;">
                    Управление настраиваемыми дашбордами и виджетами аналитики
                </p>
                <div class="settings-actions">
                    <button class="btn btn-primary" id="create-dashboard-btn">+ Создать дашборд</button>
                    <button class="btn btn-secondary" id="refresh-btn">🔄 Обновить</button>
                </div>
            </div>

            <!-- Body -->
            <div class="settings-body">
                <div id="dashboards-container" class="dashboard-list">
                    <div class="loading">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Create/Edit Dashboard -->
    <div id="dashboard-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Создать дашборд</h2>
                <button class="modal-close" onclick="closeDashboardModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-error"></div>

                <form id="dashboard-form">
                    <input type="hidden" id="edit-dashboard-id">

                    <div class="form-group">
                        <label>Название дашборда *</label>
                        <input type="text" id="dashboard-name" placeholder="Например: Общая аналитика" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Тип раскладки</label>
                            <select id="dashboard-layout">
                                <option value="grid">Grid (сетка)</option>
                                <option value="vertical">Vertical (вертикально)</option>
                                <option value="horizontal">Horizontal (горизонтально)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Дашборд по умолчанию</label>
                            <select id="dashboard-default">
                                <option value="0">Нет</option>
                                <option value="1">Да</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Виджеты</label>
                        <button type="button" class="btn btn-success btn-small" id="add-widget-btn">+ Добавить виджет</button>
                        <div id="widgets-list" class="widget-list" style="margin-top: 10px;">
                            <div class="empty-state">
                                <div class="empty-state-icon">📊</div>
                                <div>Нет виджетов. Добавьте виджет чтобы начать.</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDashboardModal()">Отмена</button>
                <button class="btn btn-primary" id="save-dashboard-btn">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Modal: Widget Editor -->
    <div id="widget-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="widget-modal-title">Добавить виджет</h2>
                <button class="modal-close" onclick="closeWidgetModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="widget-modal-error"></div>

                <form id="widget-form">
                    <input type="hidden" id="edit-widget-index">

                    <div class="form-group">
                        <label>Название виджета *</label>
                        <input type="text" id="widget-title" placeholder="Например: Всего звонков" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Тип виджета *</label>
                            <select id="widget-type" required>
                                <option value="">Выберите тип...</option>
                                <option value="kpi_card">KPI карточка</option>
                                <option value="funnel_chart">Воронка</option>
                                <option value="bar_chart">Столбчатая диаграмма</option>
                                <option value="line_chart">Линейный график</option>
                                <option value="pie_chart">Круговая диаграмма</option>
                                <option value="table">Таблица</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Источник данных *</label>
                            <select id="widget-datasource" required>
                                <option value="">Выберите источник...</option>
                                <option value="conversion_funnel">Воронка конверсии</option>
                                <option value="conversion_by_managers">По менеджерам</option>
                                <option value="conversion_by_compliance">По compliance</option>
                                <option value="conversion_by_emotion">По эмоциям</option>
                                <option value="conversion_trends">Тренды конверсии</option>
                                <option value="conversion_by_templates">По шаблонам</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Ширина (колонок 1-12)</label>
                            <input type="number" id="widget-width" min="1" max="12" value="4">
                        </div>
                        <div class="form-group">
                            <label>Высота (строк)</label>
                            <input type="number" id="widget-height" min="1" max="4" value="1">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Порядок отображения</label>
                        <input type="number" id="widget-order" min="0" value="0">
                    </div>

                    <div class="widget-config-section">
                        <h4>Конфигурация виджета (JSON)</h4>
                        <textarea id="widget-config" rows="10" placeholder='{"metric": "total_calls", "format": "number"}'></textarea>
                        <div style="font-size: 11px; color: #999; margin-top: 8px;">
                            Примеры конфигурации для разных типов виджетов смотрите в документации
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeWidgetModal()">Отмена</button>
                <button class="btn btn-primary" id="save-widget-btn">Добавить</button>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard_settings.js"></script>
</body>
</html>
