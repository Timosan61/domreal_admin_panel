/**
 * Communication Metrics Loader
 * Загрузка и визуализация метрик коммуникации менеджеров
 *
 * Метрики:
 * - Частота перебиваний (Interruption Rate)
 * - Talk-to-Listen Ratio
 */

class CommunicationMetricsLoader {
    constructor() {
        this.apiUrl = '/api/communication_metrics.php';
        this.defaultPeriod = '7d';
        this.cache = {};
    }

    /**
     * Получить метрики перебиваний
     *
     * @param {Object} filters - Фильтры (period, manager, department)
     * @returns {Promise<Object>}
     */
    async getInterruptionMetrics(filters = {}) {
        const params = new URLSearchParams({
            type: 'interruptions',
            period: filters.period || this.defaultPeriod,
            manager: filters.manager || '',
            department: filters.department || ''
        });

        try {
            const response = await fetch(`${this.apiUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load interruption metrics');
            }

            return data.data;
        } catch (error) {
            console.error('Error loading interruption metrics:', error);
            throw error;
        }
    }

    /**
     * Получить метрики Talk-to-Listen ratio
     *
     * @param {Object} filters - Фильтры (period, manager, department)
     * @returns {Promise<Object>}
     */
    async getTalkListenMetrics(filters = {}) {
        const params = new URLSearchParams({
            type: 'talk_listen',
            period: filters.period || this.defaultPeriod,
            manager: filters.manager || '',
            department: filters.department || ''
        });

        try {
            const response = await fetch(`${this.apiUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load talk-listen metrics');
            }

            return data.data;
        } catch (error) {
            console.error('Error loading talk-listen metrics:', error);
            throw error;
        }
    }

    /**
     * Получить общие метрики (summary)
     *
     * @param {Object} filters - Фильтры (period, manager, department)
     * @returns {Promise<Object>}
     */
    async getSummaryMetrics(filters = {}) {
        const params = new URLSearchParams({
            type: 'summary',
            period: filters.period || this.defaultPeriod,
            manager: filters.manager || '',
            department: filters.department || ''
        });

        try {
            const response = await fetch(`${this.apiUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load summary metrics');
            }

            return data.data;
        } catch (error) {
            console.error('Error loading summary metrics:', error);
            throw error;
        }
    }

    /**
     * Отрисовка таблицы метрик перебиваний
     *
     * @param {HTMLElement} container - Контейнер для таблицы
     * @param {Object} data - Данные (managers, timeline)
     */
    renderInterruptionTable(container, data) {
        if (!data || !data.managers || data.managers.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Нет данных для отображения</div>';
            return;
        }

        const severityBadge = (severity) => {
            const badges = {
                'critical': '<span class="badge badge-danger">Критично</span>',
                'warning': '<span class="badge badge-warning">Внимание</span>',
                'good': '<span class="badge badge-success">Норма</span>'
            };
            return badges[severity] || '';
        };

        const rows = data.managers.map(manager => `
            <tr>
                <td>${manager.name}</td>
                <td>${manager.department}</td>
                <td class="text-center">${manager.calls_count}</td>
                <td class="text-center">
                    <strong>${manager.interruption_rate}%</strong>
                </td>
                <td class="text-center">
                    ${manager.total_interruptions} / ${manager.total_transitions}
                </td>
                <td class="text-center">${severityBadge(manager.severity)}</td>
            </tr>
        `).join('');

        container.innerHTML = `
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Менеджер</th>
                        <th>Отдел</th>
                        <th class="text-center">Звонков</th>
                        <th class="text-center">Частота перебиваний</th>
                        <th class="text-center">Перебиваний / Переходов</th>
                        <th class="text-center">Оценка</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        `;
    }

    /**
     * Отрисовка таблицы Talk-to-Listen метрик
     *
     * @param {HTMLElement} container - Контейнер для таблицы
     * @param {Object} data - Данные (managers, timeline)
     */
    renderTalkListenTable(container, data) {
        if (!data || !data.managers || data.managers.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Нет данных для отображения</div>';
            return;
        }

        const severityBadge = (severity) => {
            const badges = {
                'critical': '<span class="badge badge-danger">Критично</span>',
                'warning': '<span class="badge badge-warning">Внимание</span>',
                'good': '<span class="badge badge-success">Норма</span>'
            };
            return badges[severity] || '';
        };

        const rows = data.managers.map(manager => `
            <tr>
                <td>${manager.name}</td>
                <td>${manager.department}</td>
                <td class="text-center">${manager.calls_count}</td>
                <td class="text-center">
                    <strong>${manager.talk_to_listen_ratio}</strong>
                </td>
                <td class="text-center">${manager.manager_dominance}%</td>
                <td class="text-center">${severityBadge(manager.severity)}</td>
            </tr>
        `).join('');

        container.innerHTML = `
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Менеджер</th>
                        <th>Отдел</th>
                        <th class="text-center">Звонков</th>
                        <th class="text-center">Talk/Listen Ratio</th>
                        <th class="text-center">Доминирование менеджера</th>
                        <th class="text-center">Оценка</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        `;
    }

    /**
     * Отрисовка таблицы общих метрик
     *
     * @param {HTMLElement} container - Контейнер для таблицы
     * @param {Object} data - Данные (managers)
     */
    renderSummaryTable(container, data) {
        if (!data || !data.managers || data.managers.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Нет данных для отображения</div>';
            return;
        }

        const severityBadge = (severity) => {
            const badges = {
                'critical': '<span class="badge badge-danger">Критично</span>',
                'warning': '<span class="badge badge-warning">Внимание</span>',
                'good': '<span class="badge badge-success">Норма</span>'
            };
            return badges[severity] || '';
        };

        const rows = data.managers.map(manager => `
            <tr>
                <td>${manager.name}</td>
                <td>${manager.department}</td>
                <td class="text-center">${manager.calls_count}</td>
                <td class="text-center">${manager.interruption_rate}%</td>
                <td class="text-center">${manager.talk_to_listen_ratio}</td>
                <td class="text-center">${severityBadge(manager.severity)}</td>
            </tr>
        `).join('');

        container.innerHTML = `
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Менеджер</th>
                        <th>Отдел</th>
                        <th class="text-center">Звонков</th>
                        <th class="text-center">Перебивания</th>
                        <th class="text-center">Talk/Listen</th>
                        <th class="text-center">Оценка</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        `;
    }

    /**
     * Отрисовка графика Timeline (Chart.js)
     *
     * @param {HTMLCanvasElement} canvas - Canvas для Chart.js
     * @param {Array} timeline - Данные timeline
     * @param {String} metric - Тип метрики (interruption_rate или talk_to_listen_ratio)
     */
    renderTimelineChart(canvas, timeline, metric = 'interruption_rate') {
        if (!timeline || timeline.length === 0) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const labels = timeline.map(item => item.date);
        const dataKey = metric === 'interruption_rate'
            ? 'avg_interruption_rate'
            : 'avg_talk_to_listen_ratio';
        const dataValues = timeline.map(item => item[dataKey]);

        const chartLabel = metric === 'interruption_rate'
            ? 'Средняя частота перебиваний (%)'
            : 'Среднее соотношение Talk/Listen';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: chartLabel,
                    data: dataValues,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: chartLabel
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Дата'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        enabled: true
                    }
                }
            }
        });
    }

    /**
     * Экспорт данных в CSV
     *
     * @param {Object} data - Данные для экспорта
     * @param {String} filename - Имя файла
     */
    exportToCSV(data, filename = 'communication_metrics.csv') {
        if (!data || !data.managers) {
            console.error('No data to export');
            return;
        }

        const headers = ['Менеджер', 'Отдел', 'Звонков', 'Перебивания (%)', 'Talk/Listen', 'Оценка'];
        const rows = data.managers.map(manager => [
            manager.name,
            manager.department,
            manager.calls_count,
            manager.interruption_rate || '-',
            manager.talk_to_listen_ratio || '-',
            manager.severity
        ]);

        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }
}

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CommunicationMetricsLoader;
}
