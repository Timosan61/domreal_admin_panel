<?php
session_start();
require_once 'auth/session.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Динамика сделок - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn"></button>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="risk-analysis-container">
            <header class="page-header">
                <h1>📊 Динамика сделок</h1>
                <button class="btn-primary" onclick="loadDeals()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Обновить
                </button>
            </header>

            <!-- Фильтры -->
            <div class="filters-panel">
                <form onsubmit="event.preventDefault(); loadDeals();">
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
                            <label>Статус сделки</label>
                            <select id="deal_status">
                                <option value="">Все статусы</option>
                                <option value="Горячий">🔥 Горячий</option>
                                <option value="Теплый">🟠 Теплый</option>
                                <option value="Холодный">❄️ Холодный</option>
                                <option value="Потерян">❌ Потерян</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Менеджер</label>
                            <select id="manager">
                                <option value="">Все менеджеры</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-end">
                            <button type="submit" class="btn-primary w-100">Применить</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Сводка -->
            <div class="deal-summary-cards" id="summary-cards">
                <div class="deal-summary-card">
                    <div class="deal-summary-card-title">Всего сделок</div>
                    <div class="deal-summary-card-value" id="summary-total">0</div>
                </div>
                <div class="deal-summary-card hot-border">
                    <div class="deal-summary-card-title">🔥 Горячие</div>
                    <div class="deal-summary-card-value" id="summary-hot">0</div>
                </div>
                <div class="deal-summary-card warm-border">
                    <div class="deal-summary-card-title">🟠 Теплые</div>
                    <div class="deal-summary-card-value" id="summary-warm">0</div>
                </div>
                <div class="deal-summary-card cold-border">
                    <div class="deal-summary-card-title">❄️ Холодные</div>
                    <div class="deal-summary-card-value" id="summary-cold">0</div>
                </div>
            </div>

            <!-- Таблица сделок -->
            <div class="deal-table-container">
                <table class="deal-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Дата</th>
                            <th>Менеджер</th>
                            <th>Клиент</th>
                            <th>ID сделки</th>
                            <th class="text-center">Длительность</th>
                            <th class="text-center">Статус</th>
                            <th class="text-center">Соблюдение</th>
                            <th>Резюме</th>
                            <th>Возражения</th>
                            <th>Следующие шаги</th>
                        </tr>
                    </thead>
                    <tbody id="deals-tbody">
                        <tr>
                            <td colspan="11" class="loading">Загрузка данных...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        async function loadDeals() {
            const tbody = document.getElementById('deals-tbody');
            tbody.innerHTML = '<tr><td colspan="10" class="loading">Загрузка данных...</td></tr>';

            try {
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;
                const dealStatus = document.getElementById('deal_status').value;
                const manager = document.getElementById('manager').value;

                const params = new URLSearchParams({
                    date_from: dateFrom,
                    date_to: dateTo
                });

                if (dealStatus) params.set('deal_status', dealStatus);
                if (manager) params.set('manager', manager);

                const response = await fetch(`api/analytics/deal_dynamics_list.php?${params}`);
                const result = await response.json();

                if (result.success && result.deals && result.deals.length > 0) {
                    renderDealsTable(result.deals);
                    updateSummary(result.stats);
                } else {
                    tbody.innerHTML = '<tr><td colspan="11" class="no-data">Нет данных за выбранный период</td></tr>';
                    updateSummary({total_deals: 0, hot_deals: 0, warm_deals: 0, cold_deals: 0});
                }
            } catch (error) {
                console.error('Error loading deals:', error);
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">Ошибка загрузки данных</td></tr>';
            }
        }

        function renderDealsTable(deals) {
            const tbody = document.getElementById('deals-tbody');

            tbody.innerHTML = deals.map((deal, index) => {
                const statusClass = deal.deal_status === 'Горячий' ? 'hot' :
                                   deal.deal_status === 'Теплый' ? 'warm' :
                                   deal.deal_status === 'Холодный' ? 'cold' : 'lost';

                const compliancePercent = Math.round(deal.compliance_score * 100);
                const duration = `${Math.floor(deal.call_duration_sec / 60)}:${(deal.call_duration_sec % 60).toString().padStart(2, '0')}`;

                // Ссылка на сделку в CRM (если есть ID)
                let requisitionCell = '-';
                if (deal.crm_requisition_id) {
                    const requisitionUrl = `https://app.joywork.ru/requisitions/${deal.crm_requisition_id}`;
                    requisitionCell = `<a href="${requisitionUrl}" target="_blank" class="deal-crm-link" onclick="event.stopPropagation();" title="Открыть сделку в JoyWork CRM">${deal.crm_requisition_id}</a>`;
                }

                return `
                    <tr onclick="viewCallDetails('${deal.callid}')">
                        <td>${index + 1}</td>
                        <td>${deal.call_date}</td>
                        <td>
                            <div class="deal-manager-name">${escapeHtml(deal.employee_name || '-')}</div>
                        </td>
                        <td>${deal.client_phone || '-'}</td>
                        <td>${requisitionCell}</td>
                        <td class="text-center">${duration}</td>
                        <td class="text-center">
                            <span class="deal-status-badge ${statusClass}">
                                ${deal.deal_status || '-'}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="deal-compliance-wrapper">
                                <div class="deal-compliance-bar">
                                    <div class="deal-compliance-fill" style="width: ${compliancePercent}%"></div>
                                </div>
                                <span class="deal-compliance-value">${compliancePercent}%</span>
                            </div>
                        </td>
                        <td>
                            <div class="deal-summary-text-cell" title="${escapeHtml(deal.summary_text || '')}">
                                ${escapeHtml(deal.summary_text || '-')}
                            </div>
                        </td>
                        <td>
                            <div class="deal-objections-cell" title="${escapeHtml(deal.deal_objections || '')}">
                                ${deal.deal_objections ? '⚠️ ' + escapeHtml(deal.deal_objections) : '-'}
                            </div>
                        </td>
                        <td>
                            <div class="deal-next-steps-cell" title="${escapeHtml(deal.deal_next_steps || '')}">
                                ${deal.deal_next_steps ? '📋 ' + escapeHtml(deal.deal_next_steps.replace(/\n/g, ' | ')) : '-'}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updateSummary(stats) {
            document.getElementById('summary-total').textContent = stats.total_deals || 0;
            document.getElementById('summary-hot').textContent = stats.hot_deals || 0;
            document.getElementById('summary-warm').textContent = stats.warm_deals || 0;
            document.getElementById('summary-cold').textContent = stats.cold_deals || 0;
        }

        function viewCallDetails(callid) {
            window.location.href = `/index.php?callid=${callid}`;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function loadManagers() {
            try {
                const response = await fetch('api/filters/managers.php');
                const result = await response.json();

                if (result.success && result.managers) {
                    const select = document.getElementById('manager');
                    result.managers.forEach(mgr => {
                        const option = document.createElement('option');
                        option.value = mgr.employee_name;
                        option.textContent = mgr.employee_name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load managers:', error);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadManagers();
            loadDeals();
        });
    </script>
</body>
</html>
