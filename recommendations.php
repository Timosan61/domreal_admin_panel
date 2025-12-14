<?php
/**
 * Страница рекомендаций по обучению
 * Closed-Loop Learning System
 */

require_once __DIR__ . '/auth/session.php';
checkAuth();

$pageTitle = 'Рекомендации по обучению';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - AILOCA</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .recommendations-container {
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg, #fff);
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 28px;
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }

        .stat-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .stat-card.critical h3 { color: #dc3545; }
        .stat-card.warning h3 { color: #ffc107; }
        .stat-card.success h3 { color: #28a745; }

        .filters-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters-bar select,
        .filters-bar input {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .recommendations-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .recommendations-table th,
        .recommendations-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .recommendations-table th {
            background: var(--header-bg);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        .recommendations-table tr:hover {
            background: var(--hover-bg);
        }

        .priority-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-critical { background: #dc3545; color: white; }
        .priority-high { background: #fd7e14; color: white; }
        .priority-medium { background: #ffc107; color: #212529; }
        .priority-low { background: #6c757d; color: white; }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-pending { background: #e9ecef; color: #495057; }
        .status-sent { background: #cce5ff; color: #004085; }
        .status-viewed { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }

        .score-cell {
            font-weight: 600;
        }

        .score-low { color: #dc3545; }
        .score-medium { color: #ffc107; }
        .score-high { color: #28a745; }

        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 4px;
        }

        .action-btn.complete { background: #28a745; color: white; }
        .action-btn.resend { background: #17a2b8; color: white; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .trend-indicator {
            display: inline-block;
            margin-left: 4px;
        }

        .trend-improving { color: #28a745; }
        .trend-declining { color: #dc3545; }
        .trend-stable { color: #6c757d; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            border-radius: 4px;
            cursor: pointer;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination button.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="recommendations-container">
                <h1>📚 Рекомендации по обучению</h1>
                <p style="color: var(--text-secondary); margin-bottom: 24px;">
                    Closed-Loop Learning System: автоматические рекомендации на основе анализа звонков
                </p>

                <!-- Статистика -->
                <div class="stats-grid" id="stats-grid">
                    <div class="stat-card">
                        <h3 id="stat-pending">-</h3>
                        <p>Ожидают</p>
                    </div>
                    <div class="stat-card warning">
                        <h3 id="stat-critical">-</h3>
                        <p>Критичных</p>
                    </div>
                    <div class="stat-card">
                        <h3 id="stat-sent">-</h3>
                        <p>Отправлено в Moodle</p>
                    </div>
                    <div class="stat-card success">
                        <h3 id="stat-completed">-</h3>
                        <p>Выполнено</p>
                    </div>
                    <div class="stat-card">
                        <h3 id="stat-roi">-%</h3>
                        <p>Эффективность</p>
                    </div>
                </div>

                <!-- Фильтры -->
                <div class="filters-bar">
                    <select id="filter-status">
                        <option value="">Все статусы</option>
                        <option value="pending">Ожидает</option>
                        <option value="sent">Отправлено</option>
                        <option value="viewed">Просмотрено</option>
                        <option value="completed">Выполнено</option>
                        <option value="expired">Истекло</option>
                    </select>

                    <select id="filter-priority">
                        <option value="">Все приоритеты</option>
                        <option value="critical">Критический</option>
                        <option value="high">Высокий</option>
                        <option value="medium">Средний</option>
                        <option value="low">Низкий</option>
                    </select>

                    <select id="filter-manager">
                        <option value="">Все менеджеры</option>
                    </select>

                    <select id="filter-skill">
                        <option value="">Все навыки</option>
                    </select>

                    <button onclick="loadRecommendations()" style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer;">
                        Применить
                    </button>
                </div>

                <!-- Таблица рекомендаций -->
                <table class="recommendations-table">
                    <thead>
                        <tr>
                            <th>Приоритет</th>
                            <th>Менеджер</th>
                            <th>Навык</th>
                            <th>Текущий</th>
                            <th>Целевой</th>
                            <th>Триггер</th>
                            <th>Статус</th>
                            <th>Создано</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="recommendations-tbody">
                        <tr>
                            <td colspan="9" class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                <p>Загрузка рекомендаций...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Пагинация -->
                <div class="pagination" id="pagination"></div>
            </div>
        </main>
    </div>

    <script>
        let currentPage = 1;
        const pageSize = 20;
        let totalItems = 0;

        // Загрузка статистики
        async function loadStats() {
            try {
                const response = await fetch('/api/recommendations.php?action=stats');
                const result = await response.json();

                if (result.success) {
                    const data = result.data;

                    document.getElementById('stat-pending').textContent =
                        (data.by_status.pending || 0) + (data.by_status.sent || 0);
                    document.getElementById('stat-critical').textContent =
                        data.by_priority.critical || 0;
                    document.getElementById('stat-sent').textContent =
                        data.by_status.sent || 0;
                    document.getElementById('stat-completed').textContent =
                        data.by_status.completed || 0;
                    document.getElementById('stat-roi').textContent =
                        data.roi.success_rate + '%';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Загрузка фильтров
        async function loadFilters() {
            try {
                // Менеджеры
                const managersResponse = await fetch('/api/recommendations.php?action=managers');
                const managersResult = await managersResponse.json();
                if (managersResult.success) {
                    const select = document.getElementById('filter-manager');
                    managersResult.data.forEach(m => {
                        const option = document.createElement('option');
                        option.value = m.employee_full_name;
                        option.textContent = `${m.employee_full_name} (${m.active_recommendations})`;
                        select.appendChild(option);
                    });
                }

                // Навыки
                const skillsResponse = await fetch('/api/recommendations.php?action=skills');
                const skillsResult = await skillsResponse.json();
                if (skillsResult.success) {
                    const select = document.getElementById('filter-skill');
                    skillsResult.data.forEach(s => {
                        const option = document.createElement('option');
                        option.value = s.skill_code;
                        option.textContent = `${s.skill_name || s.skill_code} (${s.active_recommendations})`;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading filters:', error);
            }
        }

        // Загрузка рекомендаций
        async function loadRecommendations(page = 1) {
            currentPage = page;
            const tbody = document.getElementById('recommendations-tbody');

            try {
                const params = new URLSearchParams({
                    action: 'list',
                    limit: pageSize,
                    offset: (page - 1) * pageSize
                });

                const status = document.getElementById('filter-status').value;
                const priority = document.getElementById('filter-priority').value;
                const manager = document.getElementById('filter-manager').value;
                const skill = document.getElementById('filter-skill').value;

                if (status) params.append('status', status);
                if (priority) params.append('priority', priority);
                if (manager) params.append('manager', manager);
                if (skill) params.append('skill', skill);

                const response = await fetch(`/api/recommendations.php?${params}`);
                const result = await response.json();

                if (result.success) {
                    totalItems = result.total;
                    renderRecommendations(result.data);
                    renderPagination();
                }
            } catch (error) {
                console.error('Error loading recommendations:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="empty-state">
                            <p>Ошибка загрузки данных</p>
                        </td>
                    </tr>
                `;
            }
        }

        // Отрисовка таблицы
        function renderRecommendations(recommendations) {
            const tbody = document.getElementById('recommendations-tbody');

            if (!recommendations.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Нет рекомендаций по выбранным фильтрам</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = recommendations.map(rec => `
                <tr>
                    <td>
                        <span class="priority-badge priority-${rec.priority}">
                            ${getPriorityLabel(rec.priority)}
                        </span>
                    </td>
                    <td>${escapeHtml(rec.employee_full_name)}</td>
                    <td>
                        ${escapeHtml(rec.skill_name || rec.skill_code)}
                        ${rec.skill_category ? `<br><small style="color: var(--text-secondary)">${rec.skill_category}</small>` : ''}
                    </td>
                    <td class="score-cell ${getScoreClass(rec.current_score)}">
                        ${rec.current_score.toFixed(1)}%
                    </td>
                    <td class="score-cell">
                        ${rec.target_score.toFixed(1)}%
                    </td>
                    <td>
                        ${getTriggerLabel(rec.trigger_type)}
                    </td>
                    <td>
                        <span class="status-badge status-${rec.status}">
                            ${getStatusLabel(rec.status)}
                        </span>
                        ${rec.moodle_webhook_sent ? '<br><small>✅ Moodle</small>' : ''}
                    </td>
                    <td>
                        ${formatDate(rec.created_at)}
                        ${rec.hours_pending > 24 ? `<br><small style="color: #dc3545">${rec.hours_pending}ч назад</small>` : ''}
                    </td>
                    <td>
                        ${rec.status !== 'completed' ? `
                            <button class="action-btn complete" onclick="completeRecommendation('${rec.recommendation_id}')">
                                ✓
                            </button>
                        ` : ''}
                        ${!rec.moodle_webhook_sent && rec.status !== 'completed' ? `
                            <button class="action-btn resend" onclick="resendRecommendation('${rec.recommendation_id}')">
                                ↻
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }

        // Пагинация
        function renderPagination() {
            const pagination = document.getElementById('pagination');
            const totalPages = Math.ceil(totalItems / pageSize);

            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let html = '';

            html += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="loadRecommendations(${currentPage - 1})">←</button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `<button class="${i === currentPage ? 'active' : ''}" onclick="loadRecommendations(${i})">${i}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<span>...</span>`;
                }
            }

            html += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="loadRecommendations(${currentPage + 1})">→</button>`;

            pagination.innerHTML = html;
        }

        // Действия
        async function completeRecommendation(recId) {
            const score = prompt('Введите итоговый показатель навыка (0-100):');
            if (score === null) return;

            try {
                const response = await fetch('/api/recommendations.php?action=complete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recommendation_id: recId,
                        completion_score: parseFloat(score) || null
                    })
                });

                const result = await response.json();
                if (result.success) {
                    loadRecommendations(currentPage);
                    loadStats();
                }
            } catch (error) {
                console.error('Error completing recommendation:', error);
            }
        }

        async function resendRecommendation(recId) {
            if (!confirm('Повторно отправить рекомендацию в Moodle?')) return;

            try {
                const response = await fetch('/api/recommendations.php?action=resend', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ recommendation_id: recId })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Рекомендация поставлена в очередь на отправку');
                    loadRecommendations(currentPage);
                }
            } catch (error) {
                console.error('Error resending recommendation:', error);
            }
        }

        // Helpers
        function getPriorityLabel(priority) {
            const labels = {
                critical: 'Критический',
                high: 'Высокий',
                medium: 'Средний',
                low: 'Низкий'
            };
            return labels[priority] || priority;
        }

        function getStatusLabel(status) {
            const labels = {
                pending: 'Ожидает',
                sent: 'Отправлено',
                viewed: 'Просмотрено',
                completed: 'Выполнено',
                expired: 'Истекло'
            };
            return labels[status] || status;
        }

        function getTriggerLabel(trigger) {
            const labels = {
                threshold: '📉 Порог',
                consecutive_fails: '❌ Подряд',
                trend: '📊 Тренд',
                manual: '👤 Вручную'
            };
            return labels[trigger] || trigger;
        }

        function getScoreClass(score) {
            if (score < 30) return 'score-low';
            if (score < 70) return 'score-medium';
            return 'score-high';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadFilters();
            loadRecommendations();
        });
    </script>
</body>
</html>
