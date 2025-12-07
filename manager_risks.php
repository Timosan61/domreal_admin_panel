<?php
session_start();
require_once 'auth/session.php';
checkAuth(); // Проверка авторизации
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анализ рисков менеджеров - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .risk-analysis-container {
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .page-header h1 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .summary-card-title {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .summary-card-value {
            font-size: 32px;
            font-weight: bold;
            color: #111827;
        }

        .summary-card-subtitle {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .summary-card.critical {
            border-left: 4px solid #dc2626;
        }

        .summary-card.high {
            border-left: 4px solid #ea580c;
        }

        .risk-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .risk-table {
            width: 100%;
            border-collapse: collapse;
        }

        .risk-table thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .risk-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .risk-table th.text-center {
            text-align: center;
        }

        .risk-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }

        .risk-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .risk-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #111827;
        }

        .risk-table td.text-center {
            text-align: center;
        }

        .risk-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
        }

        .risk-badge.critical {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a533;
        }

        .risk-badge.high {
            background-color: #ffedd5;
            color: #ea580c;
            border: 1px solid #fb923c33;
        }

        .risk-badge.medium {
            background-color: #fef3c7;
            color: #ca8a04;
            border: 1px solid #fbbf2433;
        }

        .risk-badge.low {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #4ade8033;
        }

        .risk-score-bar {
            width: 100px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .risk-score-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .risk-score-fill.critical {
            background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%);
        }

        .risk-score-fill.high {
            background: linear-gradient(90deg, #ea580c 0%, #f97316 100%);
        }

        .risk-score-fill.medium {
            background: linear-gradient(90deg, #ca8a04 0%, #eab308 100%);
        }

        .risk-score-fill.low {
            background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%);
        }

        .risk-categories {
            font-size: 12px;
            color: #6b7280;
            max-width: 300px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
            font-style: italic;
        }

        .manager-name {
            font-weight: 500;
            color: #111827;
        }

        .department-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #f3f4f6;
            border-radius: 4px;
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* Модальные окна */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #9ca3af;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .modal-body {
            padding: 24px;
        }

        .call-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .call-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .call-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .call-card-title {
            font-weight: 600;
            font-size: 15px;
            color: #111827;
        }

        .call-card-badges {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .call-card-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            font-size: 13px;
            color: #6b7280;
        }

        .alert-item {
            background: #f9fafb;
            border-left: 4px solid #e5e7eb;
            padding: 16px;
            margin-bottom: 16px;
            border-radius: 6px;
        }

        .alert-item.critical {
            border-left-color: #dc2626;
            background: #fef2f2;
        }

        .alert-item.high {
            border-left-color: #ea580c;
            background: #fff7ed;
        }

        .alert-item.medium {
            border-left-color: #ca8a04;
            background: #fefce8;
        }

        .alert-item.low {
            border-left-color: #16a34a;
            background: #f0fdf4;
        }

        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .alert-title {
            font-weight: 600;
            font-size: 15px;
            color: #111827;
        }

        .alert-evidence {
            background: white;
            padding: 12px;
            border-radius: 6px;
            font-style: italic;
            color: #374151;
            margin-top: 8px;
            border: 1px solid #e5e7eb;
        }

        .alert-meta {
            display: flex;
            gap: 16px;
            margin-top: 8px;
            font-size: 13px;
            color: #6b7280;
        }
    </style>
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <!-- Левая боковая панель -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="risk-analysis-container">
            <!-- Заголовок страницы -->
            <header class="page-header">
                <h1>
                    <span>🚨</span>
                    <span>Анализ рисков конфликта интересов</span>
                </h1>
                <div>
                    <button class="btn-primary" onclick="loadRiskAnalysis()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                        Обновить
                    </button>
                </div>
            </header>

            <!-- Панель фильтров -->
            <div class="filters-panel">
                <form id="filters-form" onsubmit="event.preventDefault(); loadRiskAnalysis();">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label>Дата с</label>
                            <input type="date" id="date_from" value="<?php echo date('Y-11-01'); ?>">
                        </div>

                        <div class="filter-group">
                            <label>Дата по</label>
                            <input type="date" id="date_to" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="filter-group">
                            <label>Отдел</label>
                            <select id="department">
                                <option value="">Все отделы</option>
                            </select>
                        </div>

                        <div class="filter-group" style="align-self: end;">
                            <button type="submit" class="btn-primary" style="width: 100%;">Применить</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Сводка -->
            <div class="summary-cards" id="summary-cards">
                <div class="summary-card">
                    <div class="summary-card-title">Менеджеров с алертами</div>
                    <div class="summary-card-value" id="summary-total">0</div>
                    <div class="summary-card-subtitle">за выбранный период</div>
                </div>
                <div class="summary-card critical">
                    <div class="summary-card-title">🔴 Критический риск</div>
                    <div class="summary-card-value" id="summary-critical">0</div>
                    <div class="summary-card-subtitle">требуют немедленной проверки</div>
                </div>
                <div class="summary-card high">
                    <div class="summary-card-title">🟠 Высокий риск</div>
                    <div class="summary-card-value" id="summary-high">0</div>
                    <div class="summary-card-subtitle">требуют внимания</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-title">Всего тревожных флагов</div>
                    <div class="summary-card-value" id="summary-flags">0</div>
                    <div class="summary-card-subtitle">обнаружено за период</div>
                </div>
            </div>

            <!-- Таблица менеджеров -->
            <div class="risk-table-container">
                <table class="risk-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Менеджер</th>
                            <th>Отдел</th>
                            <th class="text-center">Уровень риска</th>
                            <th class="text-center">Риск-скор</th>
                            <th class="text-center">Всего флагов</th>
                            <th class="text-center">🔴 Крит.</th>
                            <th class="text-center">🟠 Выс.</th>
                            <th class="text-center">🟡 Сред.</th>
                            <th class="text-center">🟢 Низ.</th>
                            <th class="text-center">Звонков с алертами</th>
                            <th>Топ категории рисков</th>
                        </tr>
                    </thead>
                    <tbody id="risk-tbody">
                        <tr>
                            <td colspan="12" class="loading">Загрузка данных...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Модальное окно: Список звонков менеджера -->
            <div id="calls-modal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 1000px;">
                    <div class="modal-header">
                        <h2 id="calls-modal-title">Звонки менеджера</h2>
                        <button class="modal-close" onclick="closeCallsModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="calls-list" style="max-height: 600px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>

            <!-- Модальное окно: Детали алертов звонка -->
            <div id="alert-details-modal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 900px;">
                    <div class="modal-header">
                        <h2 id="alert-details-title">Детали тревожных флагов</h2>
                        <button class="modal-close" onclick="closeAlertDetailsModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="call-info" style="margin-bottom: 20px;"></div>
                        <div id="alerts-list"></div>
                        <div id="transcript-section" style="margin-top: 30px; display: none;">
                            <h3 style="margin-bottom: 15px;">📝 Транскрипт разговора</h3>
                            <div id="transcript-text" style="background: #f9fafb; padding: 20px; border-radius: 8px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 13px; line-height: 1.6;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        // Загрузка списка отделов
        async function loadDepartments() {
            try {
                const response = await fetch('api/filters/departments.php');
                const result = await response.json();

                if (result.success && result.departments) {
                    const select = document.getElementById('department');
                    result.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.department;
                        option.textContent = dept.department;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load departments:', error);
            }
        }

        // Загрузка анализа рисков
        async function loadRiskAnalysis() {
            const tbody = document.getElementById('risk-tbody');
            tbody.innerHTML = '<tr><td colspan="12" class="loading">Загрузка данных...</td></tr>';

            try {
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;
                const department = document.getElementById('department').value;

                const params = new URLSearchParams({
                    date_from: dateFrom,
                    date_to: dateTo
                });

                if (department) {
                    params.set('department', department);
                }

                const response = await fetch(`api/analytics/manager_risk_analysis.php?${params}`);
                const result = await response.json();

                if (result.success && result.data) {
                    renderRiskTable(result.data);
                    updateSummary(result.summary);
                } else {
                    tbody.innerHTML = '<tr><td colspan="12" class="no-data">Нет данных за выбранный период</td></tr>';
                }
            } catch (error) {
                console.error('Failed to load risk analysis:', error);
                tbody.innerHTML = '<tr><td colspan="12" class="no-data">Ошибка загрузки данных</td></tr>';
            }
        }

        // Отрисовка таблицы
        function renderRiskTable(data) {
            const tbody = document.getElementById('risk-tbody');

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="no-data">✅ Отлично! Тревожных флагов не обнаружено</td></tr>';
                return;
            }

            tbody.innerHTML = data.map((manager, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <div class="manager-name" style="cursor: pointer; color: #2563eb; text-decoration: underline;"
                             onclick="showManagerCalls('${escapeHtml(manager.manager_name)}')"
                             title="Нажмите чтобы увидеть звонки">
                            ${escapeHtml(manager.manager_name || 'Неизвестный')}
                        </div>
                    </td>
                    <td>
                        <span class="department-badge">${escapeHtml(manager.department || '-')}</span>
                    </td>
                    <td class="text-center">
                        <span class="risk-badge ${manager.risk_level.toLowerCase()}">
                            ${manager.risk_level_emoji} ${manager.risk_level_text}
                        </span>
                    </td>
                    <td class="text-center">
                        <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                            <div class="risk-score-bar">
                                <div class="risk-score-fill ${manager.risk_level.toLowerCase()}"
                                     style="width: ${manager.risk_score}%"></div>
                            </div>
                            <span style="font-weight: 600; min-width: 30px;">${manager.risk_score}</span>
                        </div>
                    </td>
                    <td class="text-center" style="font-weight: 600; font-size: 16px;">
                        ${manager.total_flags}
                    </td>
                    <td class="text-center">${manager.critical_alerts || 0}</td>
                    <td class="text-center">${manager.high_alerts || 0}</td>
                    <td class="text-center">${manager.medium_alerts || 0}</td>
                    <td class="text-center">${manager.low_alerts || 0}</td>
                    <td class="text-center">
                        ${manager.calls_with_alerts} из ${manager.total_calls}
                        <span style="color: #6b7280;">(${manager.alert_percentage}%)</span>
                    </td>
                    <td>
                        <div class="risk-categories">${formatRiskCategories(manager.top_risk_categories)}</div>
                    </td>
                </tr>
            `).join('');
        }

        // Обновление сводки
        function updateSummary(summary) {
            document.getElementById('summary-total').textContent = summary.total_managers_with_alerts || 0;
            document.getElementById('summary-critical').textContent = summary.critical_managers || 0;
            document.getElementById('summary-high').textContent = summary.high_risk_managers || 0;
            document.getElementById('summary-flags').textContent = summary.total_alerts || 0;
        }

        // Форматирование категорий рисков
        function formatRiskCategories(categories) {
            if (!categories) return '—';

            const categoryNames = {
                'personal_contacts': '📱 Личные контакты',
                'offsite_meetings': '☕ Встречи вне офиса',
                'bypass_procedures': '⚠️ Обход процедур',
                'company_criticism': '👎 Критика компании',
                'switching_to_self': '🎯 Монополизация',
                'hiding_information': '🔒 Скрытие информации',
                'channel_switching': '💬 Смена канала связи',
                'suspicious_activity': '🔍 Подозрительная активность',
                'financial_manipulation': '💰 Финансовые манипуляции',
                'preparation_to_leave': '🚪 Подготовка к уходу'
            };

            return categories.split(', ')
                .map(cat => categoryNames[cat] || cat)
                .join(', ');
        }

        // Экранирование HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Показать звонки менеджера
        async function showManagerCalls(managerName) {
            const modal = document.getElementById('calls-modal');
            const title = document.getElementById('calls-modal-title');
            const callsList = document.getElementById('calls-list');

            title.textContent = `Звонки: ${managerName}`;
            callsList.innerHTML = '<div class="loading">Загрузка звонков...</div>';
            modal.style.display = 'flex';

            try {
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;

                const params = new URLSearchParams({
                    manager_name: managerName,
                    date_from: dateFrom,
                    date_to: dateTo
                });

                const response = await fetch(`api/analytics/manager_calls_with_alerts.php?${params}`);
                const result = await response.json();

                if (result.success && result.calls.length > 0) {
                    callsList.innerHTML = result.calls.map(call => `
                        <div class="call-card" onclick="showCallAlertDetails('${call.callid}')">
                            <div class="call-card-header">
                                <div class="call-card-title">
                                    ${call.alert_badge} Звонок ${call.callid}
                                </div>
                                <div class="call-card-badges">
                                    <span class="risk-badge ${call.max_alert_level.toLowerCase()}">
                                        ${call.total_alerts} ${call.total_alerts === 1 ? 'флаг' : call.total_alerts < 5 ? 'флага' : 'флагов'}
                                    </span>
                                </div>
                            </div>
                            <div class="call-card-info">
                                <div><strong>Дата:</strong> ${call.call_date_formatted}</div>
                                <div><strong>Клиент:</strong> ${call.client_phone}</div>
                                <div><strong>Длительность:</strong> ${call.duration_formatted}</div>
                                <div><strong>Направление:</strong> ${call.direction === 'INBOUND' ? 'Входящий' : 'Исходящий'}</div>
                            </div>
                            <div style="margin-top: 8px; font-size: 13px; color: #6b7280;">
                                🔴 ${call.critical_count || 0} | 🟠 ${call.high_count || 0} | 🟡 ${call.medium_count || 0} | 🟢 ${call.low_count || 0}
                            </div>
                        </div>
                    `).join('');
                } else {
                    callsList.innerHTML = '<div class="no-data">Звонков с алертами не найдено</div>';
                }
            } catch (error) {
                console.error('Error loading calls:', error);
                callsList.innerHTML = '<div class="no-data">Ошибка загрузки данных</div>';
            }
        }

        function closeCallsModal() {
            document.getElementById('calls-modal').style.display = 'none';
        }

        // Показать детали алертов звонка
        async function showCallAlertDetails(callid) {
            const modal = document.getElementById('alert-details-modal');
            const title = document.getElementById('alert-details-title');
            const callInfo = document.getElementById('call-info');
            const alertsList = document.getElementById('alerts-list');
            const transcriptSection = document.getElementById('transcript-section');
            const transcriptText = document.getElementById('transcript-text');

            title.textContent = `Детали звонка ${callid}`;
            callInfo.innerHTML = '<div class="loading">Загрузка деталей...</div>';
            alertsList.innerHTML = '';
            transcriptSection.style.display = 'none';
            modal.style.display = 'flex';

            try {
                const response = await fetch(`api/analytics/call_alert_details.php?callid=${callid}`);
                const result = await response.json();

                if (result.success) {
                    const call = result.call;

                    // Информация о звонке
                    callInfo.innerHTML = `
                        <div style="background: #f9fafb; padding: 16px; border-radius: 8px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 14px;">
                                <div><strong>Менеджер:</strong> ${escapeHtml(call.employee_name)}</div>
                                <div><strong>Клиент:</strong> ${call.client_phone}</div>
                                <div><strong>Длительность:</strong> ${Math.floor(call.duration_sec / 60)}:${(call.duration_sec % 60).toString().padStart(2, '0')}</div>
                                <div><strong>Направление:</strong> ${call.direction === 'INBOUND' ? 'Входящий ⬇️' : 'Исходящий ⬆️'}</div>
                            </div>
                        </div>
                    `;

                    // Список алертов
                    if (result.alerts.length > 0) {
                        alertsList.innerHTML = `
                            <div style="margin-bottom: 16px;">
                                <h3 style="margin: 0 0 16px 0;">🚨 Обнаруженные риски (${result.total_alerts})</h3>
                            </div>
                            ${result.alerts.map(alert => `
                                <div class="alert-item ${alert.alert_level.toLowerCase()}">
                                    <div class="alert-header">
                                        <div>
                                            <div class="alert-title">
                                                ${alert.level_emoji} ${alert.category_emoji} ${alert.category_name}
                                            </div>
                                            <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                                ${alert.scenario_name}
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 12px; font-weight: 600; color: #6b7280;">
                                                ${alert.level_text}
                                            </div>
                                            <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">
                                                Уверенность: ${alert.confidence_percent}%
                                            </div>
                                        </div>
                                    </div>
                                    ${alert.evidence_text ? `
                                        <div class="alert-evidence">
                                            💬 "${alert.evidence_text}"
                                        </div>
                                    ` : ''}
                                    <div class="alert-meta">
                                        ${alert.evidence_timestamp ? `<span>⏱️ ${alert.evidence_timestamp}</span>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        `;
                    }

                    // Транскрипт
                    if (call.transcript_text) {
                        transcriptSection.style.display = 'block';
                        transcriptText.textContent = call.transcript_text;
                    }
                } else {
                    callInfo.innerHTML = '<div class="no-data">Ошибка загрузки данных</div>';
                }
            } catch (error) {
                console.error('Error loading alert details:', error);
                callInfo.innerHTML = '<div class="no-data">Ошибка загрузки данных</div>';
            }
        }

        function closeAlertDetailsModal() {
            document.getElementById('alert-details-modal').style.display = 'none';
        }

        // Закрытие модалок по клику вне контента
        window.onclick = function(event) {
            const callsModal = document.getElementById('calls-modal');
            const alertModal = document.getElementById('alert-details-modal');

            if (event.target === callsModal) {
                closeCallsModal();
            }
            if (event.target === alertModal) {
                closeAlertDetailsModal();
            }
        }

        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            loadDepartments();
            loadRiskAnalysis();
        });
    </script>
</body>
</html>
