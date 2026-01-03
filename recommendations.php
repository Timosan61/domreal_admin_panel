<?php
/**
 * Страница рекомендаций по обучению
 * Closed-Loop Learning System
 */

require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/permissions.php';
checkAuth();
requirePageAccess('recommendations');

$pageTitle = 'Рекомендации по обучению';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - AILOCA</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/fip-wheel.js"></script>
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

        /* Workflow Tabs */
        .workflow-tab {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-secondary);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .workflow-tab:hover {
            color: var(--text-primary);
            background: var(--hover-bg);
        }

        .workflow-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }

        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            background: var(--border-color);
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
        }

        .workflow-tab.active .tab-badge {
            background: var(--primary-color);
            color: white;
        }

        .tab-content {
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Approval checkbox */
        .approval-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Bitrix status badges */
        .bitrix-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .bitrix-badge.has-mapping { background: #d4edda; color: #155724; }
        .bitrix-badge.no-mapping { background: #fff3cd; color: #856404; }
        .bitrix-badge.task-created { background: #cce5ff; color: #004085; }
        .bitrix-badge.task-completed { background: #d4edda; color: #155724; }

        /* Moodle status badges */
        .moodle-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
        }

        .moodle-badge.pending { background: #e9ecef; color: #495057; }
        .moodle-badge.passed { background: #d4edda; color: #155724; }

        /* Improvement indicator */
        .improvement-positive { color: #28a745; }
        .improvement-negative { color: #dc3545; }
    </style>
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="recommendations-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h1 style="margin: 0;">📚 Рекомендации по обучению</h1>
                    <a href="/learning_loop_reference.php" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        <span style="font-size: 16px;">❓</span> Справка
                    </a>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 16px;">
                    Closed-Loop Learning System: автоматические рекомендации на основе анализа звонков
                </p>

                <!-- Workflow Stats -->
                <div class="stats-grid" id="workflow-stats">
                    <div class="stat-card" id="stat-pending">
                        <h3>-</h3>
                        <p>Ожидают подтверждения</p>
                    </div>
                    <div class="stat-card" id="stat-approved">
                        <h3>-</h3>
                        <p>На обучении</p>
                    </div>
                    <div class="stat-card" id="stat-completed">
                        <h3>-</h3>
                        <p>Завершено</p>
                    </div>
                    <div class="stat-card critical" id="stat-critical">
                        <h3>-</h3>
                        <p>Критических</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="workflow-tabs" style="display: flex; gap: 0; border-bottom: 2px solid var(--border-color); margin-bottom: 20px;">
                    <button class="workflow-tab active" onclick="switchTab('pending')" data-tab="pending">
                        🔔 Подтверждение РОП <span class="tab-badge" id="badge-pending">0</span>
                    </button>
                    <button class="workflow-tab" onclick="switchTab('in-progress')" data-tab="in-progress">
                        📖 На обучении <span class="tab-badge" id="badge-in-progress">0</span>
                    </button>
                    <button class="workflow-tab" onclick="switchTab('errors')" data-tab="errors">
                        📋 Журнал ошибок
                    </button>
                    <button class="workflow-tab" onclick="switchTab('completed')" data-tab="completed">
                        ✅ Завершённые
                    </button>
                </div>

                <!-- TAB: Pending Approvals -->
                <div class="tab-content" id="tab-pending" style="display: block;">
                    <div style="background: var(--card-bg); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">🔔 Рекомендации на подтверждение РОП</h2>
                            <button onclick="bulkApproveSelected()" class="action-btn" style="background: #28a745; color: white; padding: 8px 16px;">
                                ✓ Подтвердить выбранные
                            </button>
                        </div>
                        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                            После подтверждения менеджеру будет создана задача в Bitrix24 на прохождение обучения в Moodle
                        </p>
                        <table class="recommendations-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all-pending" onchange="toggleSelectAll(this)"></th>
                                    <th>Менеджер</th>
                                    <th>Навык</th>
                                    <th style="width: 80px;">Текущий %</th>
                                    <th style="width: 80px;">Приоритет</th>
                                    <th style="width: 100px;">Ожидание</th>
                                    <th style="width: 80px;">Bitrix</th>
                                    <th style="width: 150px;">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="pending-approvals-tbody">
                                <tr><td colspan="8" style="text-align: center; padding: 20px;">Загрузка...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB: In Progress -->
                <div class="tab-content" id="tab-in-progress" style="display: none;">
                    <div style="background: var(--card-bg); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h2 style="margin: 0 0 16px 0; font-size: 18px; color: var(--text-primary);">📖 Менеджеры на обучении</h2>
                        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                            Задача в Bitrix24 автоматически закроется после прохождения теста в Moodle
                        </p>
                        <table class="recommendations-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Менеджер</th>
                                    <th>Навык</th>
                                    <th style="width: 80px;">Текущий %</th>
                                    <th>Подтвердил</th>
                                    <th>Bitrix задача</th>
                                    <th>Moodle тест</th>
                                    <th style="width: 100px;">Дней в работе</th>
                                </tr>
                            </thead>
                            <tbody id="in-progress-tbody">
                                <tr><td colspan="7" style="text-align: center; padding: 20px;">Загрузка...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB: Errors Journal (existing) -->
                <div class="tab-content" id="tab-errors" style="display: none;">
                <div class="detailed-errors-section" style="background: var(--card-bg); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">📋 Детальный журнал ошибок</h2>
                        <span id="detailed-errors-count" style="font-size: 14px; color: var(--text-secondary);"></span>
                    </div>

                    <table class="recommendations-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th style="min-width: 120px;">Менеджер</th>
                                <th>Звонок</th>
                                <th style="width: 60px;">Оценка</th>
                                <th style="min-width: 150px;">Ошибки</th>
                                <th style="min-width: 150px;">Уроки</th>
                                <th style="width: 100px;">Статус</th>
                                <th style="width: 100px;">Дата</th>
                            </tr>
                        </thead>
                        <tbody id="detailed-errors-tbody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                    Загрузка данных...
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Пагинация детальных ошибок -->
                    <div class="pagination" id="detailed-errors-pagination" style="margin-top: 16px;"></div>
                </div>
                </div>

                <!-- TAB: Completed -->
                <div class="tab-content" id="tab-completed" style="display: none;">
                    <div style="background: var(--card-bg); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h2 style="margin: 0 0 16px 0; font-size: 18px; color: var(--text-primary);">✅ Завершённые рекомендации</h2>
                        <table class="recommendations-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Менеджер</th>
                                    <th>Навык</th>
                                    <th style="width: 80px;">До</th>
                                    <th style="width: 80px;">После</th>
                                    <th style="width: 80px;">Улучшение</th>
                                    <th>Moodle балл</th>
                                    <th>Завершено</th>
                                </tr>
                            </thead>
                            <tbody id="completed-tbody">
                                <tr><td colspan="7" style="text-align: center; padding: 20px;">Загрузка...</td></tr>
                            </tbody>
                        </table>
                        <div class="pagination" id="completed-pagination" style="margin-top: 16px;"></div>
                    </div>
                </div>

                <!-- Approval Modal -->
                <div id="approval-modal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
                    <div class="modal-content" style="background: var(--card-bg); border-radius: 12px; padding: 24px; max-width: 500px; width: 95%; position: relative;">
                        <button onclick="closeApprovalModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
                        <h3 id="approval-modal-title" style="margin: 0 0 16px 0;">Подтверждение рекомендации</h3>
                        <p id="approval-modal-info" style="color: var(--text-secondary); margin-bottom: 16px;"></p>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Комментарий (опционально):</label>
                            <textarea id="approval-comment" rows="3" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--input-bg); color: var(--text-primary);"></textarea>
                        </div>
                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                            <button onclick="closeApprovalModal()" style="padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 6px; background: transparent; cursor: pointer;">Отмена</button>
                            <button onclick="rejectRecommendation()" style="padding: 8px 16px; border: none; border-radius: 6px; background: #dc3545; color: white; cursor: pointer;">Отклонить</button>
                            <button onclick="confirmApproval()" style="padding: 8px 16px; border: none; border-radius: 6px; background: #28a745; color: white; cursor: pointer;">Подтвердить</button>
                        </div>
                        <input type="hidden" id="approval-rec-id">
                    </div>
                </div>

                <!-- Модальное окно колеса навыков FIP -->
                <div id="fip-modal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
                    <div class="modal-content" style="background: #1a1a2e; border-radius: 16px; padding: 24px; max-width: 900px; width: 95%; max-height: 95vh; overflow-y: auto; position: relative; border: 1px solid rgba(255,255,255,0.1);">
                        <button onclick="closeFipModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 28px; cursor: pointer; color: rgba(255,255,255,0.6);">&times;</button>

                        <h2 id="fip-modal-title" style="margin: 0 0 8px 0; font-size: 22px; color: #fff;">Колесо навыков FIP</h2>
                        <p id="fip-modal-email" style="margin: 0 0 20px 0; color: rgba(255,255,255,0.6); font-size: 14px;"></p>

                        <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start;">
                            <div style="flex: 0 0 auto;">
                                <canvas id="fip-wheel-canvas"></canvas>
                            </div>
                            <div style="flex: 1; min-width: 250px;">
                                <h4 style="margin: 0 0 12px 0; font-size: 14px; color: rgba(255,255,255,0.7);">Категории:</h4>
                                <div id="fip-categories-list"></div>

                                <h4 style="margin: 20px 0 12px 0; font-size: 14px; color: rgba(255,255,255,0.7);">Навыки:</h4>
                                <div id="fip-skills-list" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 250px; overflow-y: auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Helpers
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // =============================================================================
        // Детальный журнал ошибок (один звонок = одна строка)
        // =============================================================================
        let detailedErrorsPage = 1;
        const detailedErrorsPageSize = 20;
        let detailedErrorsTotal = 0;

        async function loadDetailedErrors(page = 1) {
            detailedErrorsPage = page;
            const tbody = document.getElementById('detailed-errors-tbody');
            const offset = (page - 1) * detailedErrorsPageSize;

            try {
                // Используем новый endpoint all_calls_with_errors
                const response = await fetch(`/api/moodle_learning_loop.php?action=all_calls_with_errors&limit=${detailedErrorsPageSize}&offset=${offset}`);
                const result = await response.json();

                if (result.success) {
                    detailedErrorsTotal = result.total;
                    document.getElementById('detailed-errors-count').textContent = `Всего звонков: ${result.total}`;
                    renderDetailedErrors(result.data);
                    renderDetailedErrorsPagination();
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: var(--text-secondary);">${result.error || 'Ошибка загрузки'}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading detailed errors:', error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: var(--text-secondary);">Ошибка подключения</td></tr>';
            }
        }

        function renderDetailedErrors(calls) {
            const tbody = document.getElementById('detailed-errors-tbody');

            if (!calls.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: var(--text-secondary);">Нет данных</td></tr>';
                return;
            }

            tbody.innerHTML = calls.map(call => {
                // Определяем цвет оценки
                let scoreClass = '';
                let scoreColor = '#28a745';
                if (call.compliance_score < 50) {
                    scoreClass = 'score-low';
                    scoreColor = '#dc3545';
                } else if (call.compliance_score < 75) {
                    scoreClass = 'score-medium';
                    scoreColor = '#ffc107';
                }

                // Формируем ошибки (объединённые в одну ячейку)
                let errorsHtml = '';
                if (call.errors && call.errors.length > 0) {
                    errorsHtml = call.errors.map(e =>
                        `<span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; display: inline-block; margin: 1px;">${escapeHtml(e.skill)}</span>`
                    ).join('');
                } else {
                    errorsHtml = '<span style="color: #28a745; font-size: 12px;">✓ Без ошибок</span>';
                }

                // Формируем уроки (объединённые в одну ячейку)
                let lessonsHtml = '';
                if (call.errors && call.errors.length > 0) {
                    const uniqueLessons = [];
                    call.errors.forEach(e => {
                        if (e.lesson_url && !uniqueLessons.find(l => l.url === e.lesson_url)) {
                            uniqueLessons.push({ url: e.lesson_url, skill: e.skill });
                        }
                    });
                    lessonsHtml = uniqueLessons.map(l =>
                        `<a href="${l.url}" target="_blank" style="color: var(--primary-color); text-decoration: none; font-size: 11px; display: inline-block; margin: 1px;">${escapeHtml(l.skill)} →</a>`
                    ).join('<br>');
                } else {
                    lessonsHtml = '<span style="color: var(--text-secondary); font-size: 12px;">—</span>';
                }

                // Статус звонка
                let statusHtml = '';
                let rowStyle = '';
                if (call.call_status === 'no_answer') {
                    statusHtml = '<span style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">📵 Недозвон</span>';
                    rowStyle = 'background: rgba(108, 117, 125, 0.05);';
                    errorsHtml = '<span style="color: #6c757d; font-size: 12px;">—</span>';
                    lessonsHtml = '<span style="color: #6c757d; font-size: 12px;">—</span>';
                } else if (call.error_count === 0) {
                    statusHtml = '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">✓ OK</span>';
                    rowStyle = 'background: rgba(40, 167, 69, 0.05);';
                } else {
                    statusHtml = `<span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">⚠ ${call.error_count} ош.</span>`;
                }

                return `
                    <tr style="${rowStyle}">
                        <td>
                            <span style="font-weight: 500;">${escapeHtml(call.manager_name)}</span>
                        </td>
                        <td>
                            <a href="${call.call_url}" target="_blank" style="color: var(--primary-color); text-decoration: none; font-size: 12px;">
                                ${escapeHtml(call.call_id.substring(0, 25))}...
                            </a>
                        </td>
                        <td style="text-align: center;">
                            <span style="font-weight: 600; color: ${scoreColor};">${call.compliance_score}%</span>
                        </td>
                        <td>${errorsHtml}</td>
                        <td>${lessonsHtml}</td>
                        <td style="text-align: center;">${statusHtml}</td>
                        <td style="font-size: 12px; white-space: nowrap;">${escapeHtml(call.call_date_formatted)}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderDetailedErrorsPagination() {
            const pagination = document.getElementById('detailed-errors-pagination');
            const totalPages = Math.ceil(detailedErrorsTotal / detailedErrorsPageSize);

            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let html = '';
            html += `<button ${detailedErrorsPage === 1 ? 'disabled' : ''} onclick="loadDetailedErrors(${detailedErrorsPage - 1})">←</button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= detailedErrorsPage - 2 && i <= detailedErrorsPage + 2)) {
                    html += `<button class="${i === detailedErrorsPage ? 'active' : ''}" onclick="loadDetailedErrors(${i})">${i}</button>`;
                } else if (i === detailedErrorsPage - 3 || i === detailedErrorsPage + 3) {
                    html += `<span>...</span>`;
                }
            }

            html += `<button ${detailedErrorsPage === totalPages ? 'disabled' : ''} onclick="loadDetailedErrors(${detailedErrorsPage + 1})">→</button>`;
            pagination.innerHTML = html;
        }

        // =============================================================================
        // Модальное окно колеса навыков FIP
        // =============================================================================
        let fipWheel = null;

        const categoryColors = {
            'Знание продукта': '#00d4aa',
            'Коммуникация': '#ff5555',
            'Техника продаж': '#ff8844',
            'Драйв и мотивация': '#44dd44',
            'Аналитика': '#4488ff'
        };

        async function showFipModal(userId) {
            const modal = document.getElementById('fip-modal');
            modal.style.display = 'flex';

            try {
                const response = await fetch(`/api/moodle_learning_loop.php?action=manager_fip_skills&user_id=${userId}`);
                const result = await response.json();

                if (result.success) {
                    document.getElementById('fip-modal-title').textContent = result.user.name;
                    document.getElementById('fip-modal-email').textContent = result.user.email;

                    // FIP Wheel - преобразуем данные навыков в формат колеса
                    const skillValues = {};
                    if (result.skills && result.skills.length) {
                        result.skills.forEach(skill => {
                            // Преобразуем 0-100 в 0-12
                            skillValues[skill.skill_name] = Math.round(skill.points / 100 * 12);
                        });
                    }
                    renderFipWheel(skillValues);

                    // Categories list
                    const categoriesList = document.getElementById('fip-categories-list');
                    categoriesList.innerHTML = Object.entries(result.categories).map(([key, cat]) => `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 6px; border-left: 3px solid ${categoryColors[cat.label] || '#6c757d'};">
                            <span style="font-size: 13px; color: #fff;">${cat.label}</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 60px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                                    <div style="width: ${cat.value}%; height: 100%; background: ${categoryColors[cat.label] || '#6c757d'};"></div>
                                </div>
                                <span style="font-weight: 600; min-width: 30px; text-align: right; color: #fff;">${cat.value}</span>
                            </div>
                        </div>
                    `).join('');

                    // Skills list
                    const skillsList = document.getElementById('fip-skills-list');
                    if (result.skills && result.skills.length) {
                        skillsList.innerHTML = result.skills.map(skill => `
                            <span style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px; font-size: 11px; color: rgba(255,255,255,0.9);">
                                ${escapeHtml(skill.skill_name)}: <strong>${skill.points}</strong>
                            </span>
                        `).join('');
                    } else {
                        skillsList.innerHTML = '<span style="color: rgba(255,255,255,0.5); font-size: 13px;">Нет данных о навыках</span>';
                    }
                } else {
                    document.getElementById('fip-modal-title').textContent = 'Ошибка';
                    document.getElementById('fip-modal-email').textContent = result.error || 'Не удалось загрузить данные';
                }
            } catch (error) {
                console.error('Error loading FIP skills:', error);
                document.getElementById('fip-modal-title').textContent = 'Ошибка подключения';
                document.getElementById('fip-modal-email').textContent = '';
            }
        }

        function renderFipWheel(skillValues) {
            const canvas = document.getElementById('fip-wheel-canvas');
            if (!canvas) return;

            // Создаём колесо с переданными значениями
            fipWheel = new FIPWheel(canvas, {
                size: 450,
                values: skillValues
            });
            fipWheel.draw();
        }

        function closeFipModal() {
            document.getElementById('fip-modal').style.display = 'none';
            fipWheel = null;
        }

        // Закрытие по клику на фон
        document.getElementById('fip-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFipModal();
            }
        });

        // =============================================================================
        // Tab Switching
        // =============================================================================
        let currentTab = 'pending';

        function switchTab(tabName) {
            currentTab = tabName;

            // Update tab buttons
            document.querySelectorAll('.workflow-tab').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = content.id === `tab-${tabName}` ? 'block' : 'none';
            });

            // Load data for selected tab
            switch(tabName) {
                case 'pending':
                    loadPendingApprovals();
                    break;
                case 'in-progress':
                    loadInProgress();
                    break;
                case 'errors':
                    loadDetailedErrors();
                    break;
                case 'completed':
                    loadCompleted();
                    break;
            }
        }

        // =============================================================================
        // Workflow Stats
        // =============================================================================
        async function loadWorkflowStats() {
            try {
                const response = await fetch('/api/training/approval_workflow.php?action=stats');
                const result = await response.json();

                if (result.success) {
                    const stats = result.data;

                    document.querySelector('#stat-pending h3').textContent = stats.pending_approval || 0;
                    document.querySelector('#stat-approved h3').textContent = stats.approved_in_progress || 0;
                    document.querySelector('#stat-completed h3').textContent = stats.completed || 0;
                    document.querySelector('#stat-critical h3').textContent = stats.critical_pending || 0;

                    // Update badges
                    document.getElementById('badge-pending').textContent = stats.pending_approval || 0;
                    document.getElementById('badge-in-progress').textContent = stats.approved_in_progress || 0;
                }
            } catch (error) {
                console.error('Error loading workflow stats:', error);
            }
        }

        // =============================================================================
        // Pending Approvals Tab
        // =============================================================================
        async function loadPendingApprovals() {
            const tbody = document.getElementById('pending-approvals-tbody');

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=pending');
                const result = await response.json();

                if (result.success) {
                    renderPendingApprovals(result.data);
                } else {
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 20px;">${result.error || 'Ошибка загрузки'}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading pending approvals:', error);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">Ошибка подключения</td></tr>';
            }
        }

        function renderPendingApprovals(recommendations) {
            const tbody = document.getElementById('pending-approvals-tbody');

            if (!recommendations.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);"><span style="font-size: 48px;">✓</span><br>Нет рекомендаций на подтверждение</td></tr>';
                return;
            }

            tbody.innerHTML = recommendations.map(rec => {
                const priorityClass = `priority-${rec.priority}`;
                const scoreColor = rec.current_score < 50 ? '#dc3545' : (rec.current_score < 75 ? '#ffc107' : '#28a745');
                const bitrixBadge = rec.has_bitrix_mapping
                    ? '<span class="bitrix-badge has-mapping">✓ Настроен</span>'
                    : '<span class="bitrix-badge no-mapping">⚠ Нет маппинга</span>';

                const hoursText = rec.hours_pending < 24
                    ? `${rec.hours_pending} ч`
                    : `${Math.floor(rec.hours_pending / 24)} дн`;

                return `
                    <tr>
                        <td><input type="checkbox" class="approval-checkbox" value="${rec.recommendation_id}"></td>
                        <td><strong>${escapeHtml(rec.employee_full_name)}</strong></td>
                        <td>${escapeHtml(rec.skill_name)}</td>
                        <td style="text-align: center; font-weight: 600; color: ${scoreColor};">${rec.current_score}%</td>
                        <td><span class="priority-badge ${priorityClass}">${rec.priority}</span></td>
                        <td style="color: ${rec.hours_pending > 48 ? '#dc3545' : 'var(--text-secondary)'};">${hoursText}</td>
                        <td>${bitrixBadge}</td>
                        <td>
                            <button onclick="openApprovalModal('${rec.recommendation_id}', '${escapeHtml(rec.employee_full_name)}', '${escapeHtml(rec.skill_name)}')"
                                    class="action-btn complete" style="margin-right: 4px;">✓</button>
                            <button onclick="quickReject('${rec.recommendation_id}')"
                                    class="action-btn" style="background: #dc3545; color: white;">✕</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // =============================================================================
        // In Progress Tab
        // =============================================================================
        async function loadInProgress() {
            const tbody = document.getElementById('in-progress-tbody');

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=approved');
                const result = await response.json();

                if (result.success) {
                    renderInProgress(result.data);
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px;">${result.error}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading in-progress:', error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Ошибка подключения</td></tr>';
            }
        }

        function renderInProgress(recommendations) {
            const tbody = document.getElementById('in-progress-tbody');

            if (!recommendations.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">Нет активных обучений</td></tr>';
                return;
            }

            tbody.innerHTML = recommendations.map(rec => {
                const scoreColor = rec.current_score < 50 ? '#dc3545' : (rec.current_score < 75 ? '#ffc107' : '#28a745');

                // Bitrix task status
                let bitrixStatus = '<span class="bitrix-badge no-mapping">—</span>';
                if (rec.bitrix_task_id) {
                    if (rec.bitrix_task_status === 'completed') {
                        bitrixStatus = '<span class="bitrix-badge task-completed">✓ Завершена</span>';
                    } else {
                        bitrixStatus = `<span class="bitrix-badge task-created">В работе</span>`;
                    }
                }

                // Moodle quiz status
                let moodleStatus = '<span class="moodle-badge pending">Ожидание</span>';
                if (rec.moodle_quiz_passed) {
                    const score = rec.moodle_quiz_score ? ` (${rec.moodle_quiz_score}%)` : '';
                    moodleStatus = `<span class="moodle-badge passed">✓ Пройден${score}</span>`;
                }

                const approvedDate = rec.approved_at ? new Date(rec.approved_at).toLocaleDateString('ru-RU') : '—';

                return `
                    <tr>
                        <td><strong>${escapeHtml(rec.employee_full_name)}</strong></td>
                        <td>${escapeHtml(rec.skill_name)}</td>
                        <td style="text-align: center; font-weight: 600; color: ${scoreColor};">${rec.current_score}%</td>
                        <td>${escapeHtml(rec.approved_by_name || '—')}<br><small style="color: var(--text-secondary);">${approvedDate}</small></td>
                        <td>${bitrixStatus}</td>
                        <td>${moodleStatus}</td>
                        <td style="text-align: center; color: ${rec.days_since_approval > 7 ? '#dc3545' : 'var(--text-secondary)'};">
                            ${rec.days_since_approval} дн
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // =============================================================================
        // Completed Tab
        // =============================================================================
        async function loadCompleted() {
            const tbody = document.getElementById('completed-tbody');

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=completed');
                const result = await response.json();

                if (result.success) {
                    renderCompleted(result.data);
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px;">${result.error}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading completed:', error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Ошибка подключения</td></tr>';
            }
        }

        function renderCompleted(recommendations) {
            const tbody = document.getElementById('completed-tbody');

            if (!recommendations.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">Нет завершённых обучений</td></tr>';
                return;
            }

            tbody.innerHTML = recommendations.map(rec => {
                const beforeColor = rec.current_score < 50 ? '#dc3545' : (rec.current_score < 75 ? '#ffc107' : '#28a745');
                const afterColor = rec.completion_score < 50 ? '#dc3545' : (rec.completion_score < 75 ? '#ffc107' : '#28a745');

                let improvementHtml = '—';
                if (rec.score_improvement !== null) {
                    const sign = rec.score_improvement > 0 ? '+' : '';
                    const cls = rec.score_improvement > 0 ? 'improvement-positive' : (rec.score_improvement < 0 ? 'improvement-negative' : '');
                    improvementHtml = `<span class="${cls}">${sign}${rec.score_improvement}%</span>`;
                }

                const moodleScore = rec.moodle_quiz_score ? `${rec.moodle_quiz_score}%` : '—';
                const completedDate = rec.completed_at ? new Date(rec.completed_at).toLocaleDateString('ru-RU') : '—';

                return `
                    <tr>
                        <td><strong>${escapeHtml(rec.employee_full_name)}</strong></td>
                        <td>${escapeHtml(rec.skill_name)}</td>
                        <td style="text-align: center; font-weight: 600; color: ${beforeColor};">${rec.current_score}%</td>
                        <td style="text-align: center; font-weight: 600; color: ${afterColor};">${rec.completion_score || '—'}%</td>
                        <td style="text-align: center; font-weight: 600;">${improvementHtml}</td>
                        <td style="text-align: center;">${moodleScore}</td>
                        <td style="font-size: 12px;">${completedDate}</td>
                    </tr>
                `;
            }).join('');
        }

        // =============================================================================
        // Approval Actions
        // =============================================================================
        function openApprovalModal(recId, managerName, skillName) {
            document.getElementById('approval-rec-id').value = recId;
            document.getElementById('approval-modal-title').textContent = 'Подтверждение рекомендации';
            document.getElementById('approval-modal-info').innerHTML = `
                <strong>Менеджер:</strong> ${escapeHtml(managerName)}<br>
                <strong>Навык:</strong> ${escapeHtml(skillName)}
            `;
            document.getElementById('approval-comment').value = '';
            document.getElementById('approval-modal').style.display = 'flex';
        }

        function closeApprovalModal() {
            document.getElementById('approval-modal').style.display = 'none';
        }

        document.getElementById('approval-modal').addEventListener('click', function(e) {
            if (e.target === this) closeApprovalModal();
        });

        async function confirmApproval() {
            const recId = document.getElementById('approval-rec-id').value;
            const comment = document.getElementById('approval-comment').value;

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=approve', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recommendation_id: recId,
                        comment: comment,
                        create_bitrix_task: true
                    })
                });

                const result = await response.json();

                if (result.success) {
                    closeApprovalModal();
                    loadPendingApprovals();
                    loadWorkflowStats();

                    if (result.bitrix_task_id) {
                        alert('Рекомендация подтверждена. Задача создана в Bitrix24.');
                    } else if (result.bitrix_error) {
                        alert('Рекомендация подтверждена, но задача в Bitrix24 не создана: ' + result.bitrix_error);
                    } else {
                        alert('Рекомендация подтверждена.');
                    }
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                console.error('Error approving:', error);
                alert('Ошибка подключения');
            }
        }

        async function rejectRecommendation() {
            const recId = document.getElementById('approval-rec-id').value;
            const comment = document.getElementById('approval-comment').value;

            if (!confirm('Отклонить эту рекомендацию?')) return;

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recommendation_id: recId,
                        comment: comment
                    })
                });

                const result = await response.json();

                if (result.success) {
                    closeApprovalModal();
                    loadPendingApprovals();
                    loadWorkflowStats();
                    alert('Рекомендация отклонена.');
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                console.error('Error rejecting:', error);
                alert('Ошибка подключения');
            }
        }

        async function quickReject(recId) {
            if (!confirm('Отклонить эту рекомендацию?')) return;

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ recommendation_id: recId })
                });

                const result = await response.json();

                if (result.success) {
                    loadPendingApprovals();
                    loadWorkflowStats();
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                console.error('Error rejecting:', error);
            }
        }

        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.approval-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        async function bulkApproveSelected() {
            const selected = Array.from(document.querySelectorAll('.approval-checkbox:checked')).map(cb => cb.value);

            if (!selected.length) {
                alert('Выберите рекомендации для подтверждения');
                return;
            }

            if (!confirm(`Подтвердить ${selected.length} рекомендаций?`)) return;

            try {
                const response = await fetch('/api/training/approval_workflow.php?action=bulk_approve', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recommendation_ids: selected,
                        create_bitrix_task: true
                    })
                });

                const result = await response.json();

                if (result.success) {
                    loadPendingApprovals();
                    loadWorkflowStats();
                    alert(result.message);
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                console.error('Error bulk approving:', error);
                alert('Ошибка подключения');
            }
        }

        // =============================================================================
        // Initialization
        // =============================================================================
        document.addEventListener('DOMContentLoaded', () => {
            loadWorkflowStats();
            loadPendingApprovals();
        });
    </script>
</body>
</html>
