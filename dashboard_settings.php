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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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
