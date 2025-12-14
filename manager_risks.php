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
                        <svg class="svg-icon-mr" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

                        <div class="filter-group align-self-end">
                            <button type="submit" class="btn-primary w-100">Применить</button>
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
            <div id="calls-modal" class="modal d-none">
                <div class="modal-content max-w-1000">
                    <div class="modal-header">
                        <h2 id="calls-modal-title">Звонки менеджера</h2>
                        <button class="modal-close" onclick="closeCallsModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="calls-list" class="risk-calls-list"></div>
                    </div>
                </div>
            </div>

            <!-- Модальное окно: Детали алертов звонка -->
            <div id="alert-details-modal" class="modal d-none">
                <div class="modal-content max-w-900">
                    <div class="modal-header">
                        <h2 id="alert-details-title">Детали тревожных флагов</h2>
                        <button class="modal-close" onclick="closeAlertDetailsModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="call-info" class="mb-4"></div>
                        <div id="alerts-list"></div>
                        <div id="transcript-section" class="transcript-section d-none">
                            <h3>📝 Транскрипт разговора</h3>
                            <div id="transcript-text" class="transcript-text"></div>
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
                        <div class="manager-name manager-name-link"
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
                        <div class="risk-score-wrapper">
                            <div class="risk-score-bar">
                                <div class="risk-score-fill ${manager.risk_level.toLowerCase()}"
                                     style="width: ${manager.risk_score}%"></div>
                            </div>
                            <span class="risk-score-value">${manager.risk_score}</span>
                        </div>
                    </td>
                    <td class="text-center total-flags-cell">
                        ${manager.total_flags}
                    </td>
                    <td class="text-center">${manager.critical_alerts || 0}</td>
                    <td class="text-center">${manager.high_alerts || 0}</td>
                    <td class="text-center">${manager.medium_alerts || 0}</td>
                    <td class="text-center">${manager.low_alerts || 0}</td>
                    <td class="text-center">
                        ${manager.calls_with_alerts} из ${manager.total_calls}
                        <span class="alert-percentage-text">(${manager.alert_percentage}%)</span>
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
            modal.classList.remove('d-none');
            modal.classList.add('d-flex');

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
                            <div class="call-card-footer">
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
            const modal = document.getElementById('calls-modal');
            modal.classList.add('d-none');
            modal.classList.remove('d-flex');
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
            transcriptSection.classList.add('d-none');
            modal.classList.remove('d-none');
            modal.classList.add('d-flex');

            try {
                const response = await fetch(`api/analytics/call_alert_details.php?callid=${callid}`);
                const result = await response.json();

                if (result.success) {
                    const call = result.call;

                    // Информация о звонке
                    callInfo.innerHTML = `
                        <div class="risk-modal-info">
                            <div class="risk-modal-info-grid">
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
                            <div class="alerts-section-header">
                                <h3 class="alerts-section-title">🚨 Обнаруженные риски (${result.total_alerts})</h3>
                            </div>
                            ${result.alerts.map(alert => `
                                <div class="alert-item ${alert.alert_level.toLowerCase()}">
                                    <div class="alert-header">
                                        <div>
                                            <div class="alert-title">
                                                ${alert.level_emoji} ${alert.category_emoji} ${alert.category_name}
                                            </div>
                                            <div class="alert-scenario-text">
                                                ${alert.scenario_name}
                                            </div>
                                        </div>
                                        <div class="alert-level-info">
                                            <div class="alert-level-text">
                                                ${alert.level_text}
                                            </div>
                                            <div class="alert-confidence-text">
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
                        transcriptSection.classList.remove('d-none');
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
            const modal = document.getElementById('alert-details-modal');
            modal.classList.add('d-none');
            modal.classList.remove('d-flex');
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
