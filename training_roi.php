<?php
/**
 * Training ROI Reports - Отчеты по эффективности обучения
 *
 * Closed-Loop Learning: Analyze → Diagnose → Train → Measure → Repeat
 */

require_once __DIR__ . '/auth/session.php';
checkAuth();
require_once __DIR__ . '/config/database.php';

$page_title = 'ROI обучения';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AILOCA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .kpi-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .kpi-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .kpi-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .kpi-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .kpi-sublabel {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        .skill-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-right: 0.25rem;
        }
        .improvement-positive {
            color: #28a745;
            font-weight: 600;
        }
        .improvement-negative {
            color: #dc3545;
            font-weight: 600;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-grow-1 p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-graph-up-arrow me-2"></i>ROI обучения
                    </h1>
                    <p class="text-muted mb-0">Эффективность системы Closed-Loop Learning</p>
                </div>
                <div class="d-flex gap-2">
                    <input type="date" id="date-from" class="form-control" style="width: 150px;">
                    <input type="date" id="date-to" class="form-control" style="width: 150px;">
                    <button class="btn btn-primary" onclick="loadData()">
                        <i class="bi bi-arrow-clockwise"></i> Обновить
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="row mb-4" id="kpi-cards">
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-value" id="kpi-total-recommendations">-</div>
                        <div class="kpi-label">Всего рекомендаций</div>
                        <div class="kpi-sublabel" id="kpi-completion-rate">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card success">
                        <div class="kpi-value" id="kpi-success-rate">-</div>
                        <div class="kpi-label">Успешность обучения</div>
                        <div class="kpi-sublabel" id="kpi-successful-count">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card info">
                        <div class="kpi-value" id="kpi-avg-improvement">-</div>
                        <div class="kpi-label">Среднее улучшение</div>
                        <div class="kpi-sublabel" id="kpi-improvement-range">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card warning">
                        <div class="kpi-value" id="kpi-managers-covered">-</div>
                        <div class="kpi-label">Менеджеров охвачено</div>
                        <div class="kpi-sublabel" id="kpi-skills-covered">-</div>
                    </div>
                </div>
            </div>

            <!-- Integration Stats -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-diagram-3 me-2"></i>Интеграции
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="display-6" id="stat-moodle">-</div>
                                    <div class="text-muted">Отправлено в Moodle</div>
                                </div>
                                <div class="col-6">
                                    <div class="display-6" id="stat-crm">-</div>
                                    <div class="text-muted">Задач в CRM</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-pie-chart me-2"></i>Статусы рекомендаций
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 150px;">
                                <canvas id="status-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-graph-up me-2"></i>Динамика рекомендаций
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="timeline-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-trophy me-2"></i>Топ навыков для обучения
                        </div>
                        <div class="card-body" id="top-skills-list">
                            <div class="text-center text-muted py-3">Загрузка...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-bullseye me-2"></i>ROI по навыкам</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="skills-table">
                                    <thead>
                                        <tr>
                                            <th>Навык</th>
                                            <th class="text-center">Рекоменд.</th>
                                            <th class="text-center">Completion</th>
                                            <th class="text-center">Улучшение</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-center text-muted">Загрузка...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-people me-2"></i>ROI по менеджерам</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="managers-table">
                                    <thead>
                                        <tr>
                                            <th>Менеджер</th>
                                            <th class="text-center">Рекоменд.</th>
                                            <th class="text-center">Критич.</th>
                                            <th class="text-center">Улучшение</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-center text-muted">Загрузка...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let statusChart = null;
        let timelineChart = null;

        // Инициализация дат
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

            document.getElementById('date-from').value = thirtyDaysAgo.toISOString().split('T')[0];
            document.getElementById('date-to').value = today.toISOString().split('T')[0];

            loadData();
        });

        async function loadData() {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;

            // Загружаем все данные параллельно
            const [summary, bySkill, byManager, timeline] = await Promise.all([
                fetchData('summary', dateFrom, dateTo),
                fetchData('by_skill', dateFrom, dateTo),
                fetchData('by_manager', dateFrom, dateTo),
                fetchData('timeline', dateFrom, dateTo)
            ]);

            if (summary.success) renderSummary(summary.data);
            if (bySkill.success) renderSkillsTable(bySkill.data);
            if (byManager.success) renderManagersTable(byManager.data);
            if (timeline.success) renderTimelineChart(timeline.data);
        }

        async function fetchData(action, dateFrom, dateTo) {
            try {
                const response = await fetch(
                    `api/training/roi_reports.php?action=${action}&date_from=${dateFrom}&date_to=${dateTo}`
                );
                return await response.json();
            } catch (error) {
                console.error(`Error fetching ${action}:`, error);
                return { success: false, error: error.message };
            }
        }

        function renderSummary(data) {
            // KPI Cards
            document.getElementById('kpi-total-recommendations').textContent =
                data.recommendations.total.toLocaleString();
            document.getElementById('kpi-completion-rate').textContent =
                `${data.recommendations.completion_rate}% выполнено`;

            document.getElementById('kpi-success-rate').textContent =
                data.trainings.success_rate ? `${data.trainings.success_rate}%` : '—';
            document.getElementById('kpi-successful-count').textContent =
                `${data.trainings.successful} из ${data.trainings.total} обучений`;

            document.getElementById('kpi-avg-improvement').textContent =
                data.trainings.avg_improvement ? `+${data.trainings.avg_improvement}%` : '—';
            document.getElementById('kpi-improvement-range').textContent =
                data.trainings.max_improvement
                    ? `от +${data.trainings.min_improvement || 0}% до +${data.trainings.max_improvement}%`
                    : 'Нет данных';

            document.getElementById('kpi-managers-covered').textContent =
                data.coverage.unique_managers;
            document.getElementById('kpi-skills-covered').textContent =
                `${data.coverage.unique_skills} навыков`;

            // Integration stats
            document.getElementById('stat-moodle').textContent =
                data.recommendations.moodle_sent;
            document.getElementById('stat-crm').textContent =
                data.recommendations.crm_tasks_created;

            // Status chart
            renderStatusChart(data.recommendations);
        }

        function renderStatusChart(rec) {
            const ctx = document.getElementById('status-chart').getContext('2d');

            if (statusChart) statusChart.destroy();

            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Выполнено', 'В ожидании', 'Отправлено', 'Просрочено'],
                    datasets: [{
                        data: [rec.completed, rec.pending, rec.sent, rec.expired],
                        backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { boxWidth: 12, padding: 8 }
                        }
                    }
                }
            });
        }

        function renderTimelineChart(data) {
            const ctx = document.getElementById('timeline-chart').getContext('2d');

            if (timelineChart) timelineChart.destroy();

            const labels = data.map(d => d.date);
            const created = data.map(d => d.recommendations_created);
            const completed = data.map(d => d.completed);
            const improvements = data.map(d => d.avg_improvement);

            timelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Создано рекомендаций',
                            data: created,
                            borderColor: '#4facfe',
                            backgroundColor: 'rgba(79, 172, 254, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Выполнено',
                            data: completed,
                            borderColor: '#28a745',
                            backgroundColor: 'transparent',
                            tension: 0.4
                        },
                        {
                            label: 'Улучшение (%)',
                            data: improvements,
                            borderColor: '#f093fb',
                            backgroundColor: 'transparent',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: { display: true, text: 'Количество' }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: { display: true, text: 'Улучшение (%)' },
                            grid: { drawOnChartArea: false }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function renderSkillsTable(data) {
            const tbody = document.querySelector('#skills-table tbody');
            const topSkillsList = document.getElementById('top-skills-list');

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Нет данных</td></tr>';
                topSkillsList.innerHTML = '<div class="text-center text-muted">Нет данных</div>';
                return;
            }

            // Table
            tbody.innerHTML = data.map(skill => `
                <tr>
                    <td>
                        <div class="fw-medium">${skill.skill_name}</div>
                        <small class="text-muted">${skill.managers} менеджеров</small>
                    </td>
                    <td class="text-center">${skill.recommendations}</td>
                    <td class="text-center">
                        <span class="badge ${skill.completion_rate >= 70 ? 'bg-success' : skill.completion_rate >= 40 ? 'bg-warning' : 'bg-danger'}">
                            ${skill.completion_rate}%
                        </span>
                    </td>
                    <td class="text-center">
                        ${skill.avg_improvement !== null
                            ? `<span class="improvement-positive">+${skill.avg_improvement}%</span>`
                            : '<span class="text-muted">—</span>'}
                    </td>
                </tr>
            `).join('');

            // Top skills list
            const topSkills = data.slice(0, 5);
            topSkillsList.innerHTML = topSkills.map((skill, i) => `
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-secondary me-2">${i + 1}</span>
                    <div class="flex-grow-1">
                        <div class="small fw-medium">${skill.skill_name}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            ${skill.recommendations} рекомендаций, ${skill.managers} менеджеров
                        </div>
                    </div>
                    <span class="badge ${skill.avg_score_before < 40 ? 'bg-danger' : 'bg-warning'}">
                        ${skill.avg_score_before}%
                    </span>
                </div>
            `).join('');
        }

        function renderManagersTable(data) {
            const tbody = document.querySelector('#managers-table tbody');

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Нет данных</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(manager => `
                <tr>
                    <td>
                        <div class="fw-medium">${manager.manager}</div>
                        <small class="text-muted">${manager.skills_count} навыков</small>
                    </td>
                    <td class="text-center">${manager.recommendations}</td>
                    <td class="text-center">
                        ${manager.critical_issues > 0
                            ? `<span class="badge bg-danger">${manager.critical_issues}</span>`
                            : '<span class="text-muted">—</span>'}
                        ${manager.high_issues > 0
                            ? `<span class="badge bg-warning ms-1">${manager.high_issues}</span>`
                            : ''}
                    </td>
                    <td class="text-center">
                        ${manager.avg_improvement !== null
                            ? `<span class="improvement-positive">+${manager.avg_improvement}%</span>`
                            : '<span class="text-muted">—</span>'}
                    </td>
                </tr>
            `).join('');
        }
    </script>
</body>
</html>
