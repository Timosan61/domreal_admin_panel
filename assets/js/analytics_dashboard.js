/**
 * Analytics Dashboard JS
 * Динамическая система дашбордов с настраиваемыми виджетами
 */

class AnalyticsDashboard {
    constructor() {
        this.currentDashboard = null;
        this.charts = {};
        this.filters = {
            date_from: null,
            date_to: null
        };

        this.init();
    }

    async init() {
        // Загрузка списка дашбордов
        await this.loadDashboardList();

        // Обработчики событий
        document.getElementById('dashboard-select').addEventListener('change', (e) => {
            this.loadDashboard(e.target.value);
        });

        document.getElementById('apply-filters').addEventListener('click', () => {
            this.applyFilters();
        });

        document.getElementById('reset-filters').addEventListener('click', () => {
            this.resetFilters();
        });

        // Установка начальных фильтров
        this.filters.date_from = document.getElementById('date-from').value;
        this.filters.date_to = document.getElementById('date-to').value;
    }

    async loadDashboardList() {
        try {
            const response = await fetch('/api/dashboards.php?action=list');
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load dashboards');
            }

            const select = document.getElementById('dashboard-select');
            select.innerHTML = '';

            result.data.forEach(dashboard => {
                const option = document.createElement('option');
                option.value = dashboard.dashboard_id;
                option.textContent = dashboard.name;
                if (dashboard.is_default) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            // Загружаем дефолтный дашборд
            const defaultDashboard = result.data.find(d => d.is_default);
            if (defaultDashboard) {
                await this.loadDashboard(defaultDashboard.dashboard_id);
            }
        } catch (error) {
            console.error('Error loading dashboard list:', error);
            this.showError('Ошибка загрузки списка дашбордов: ' + error.message);
        }
    }

    async loadDashboard(dashboardId) {
        if (!dashboardId) return;

        try {
            const response = await fetch(`/api/dashboards.php?action=get&id=${dashboardId}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load dashboard');
            }

            this.currentDashboard = result.data;
            await this.renderDashboard();
        } catch (error) {
            console.error('Error loading dashboard:', error);
            this.showError('Ошибка загрузки дашборда: ' + error.message);
        }
    }

    async renderDashboard() {
        const container = document.getElementById('dashboard-container');
        container.innerHTML = '';

        if (!this.currentDashboard || !this.currentDashboard.widgets) {
            container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px;">Нет виджетов</div>';
            return;
        }

        // Сортируем виджеты по порядку
        const widgets = this.currentDashboard.widgets.sort((a, b) => a.widget_order - b.widget_order);

        // Создаем виджеты
        for (const widget of widgets) {
            if (!widget.is_visible) continue;

            const widgetElement = await this.createWidget(widget);
            container.appendChild(widgetElement);
        }
    }

    async createWidget(widgetConfig) {
        const widget = document.createElement('div');
        widget.className = 'widget';
        widget.style.gridColumn = `span ${widgetConfig.size_width || 1}`;
        widget.style.gridRow = `span ${widgetConfig.size_height || 1}`;

        // Заголовок
        const title = document.createElement('div');
        title.className = 'widget-title';
        title.textContent = widgetConfig.title;
        widget.appendChild(title);

        // Loading state
        const loading = document.createElement('div');
        loading.className = 'widget-loading';
        loading.textContent = 'Загрузка...';
        widget.appendChild(loading);

        // Загружаем данные
        try {
            const data = await this.fetchWidgetData(widgetConfig.data_source);
            loading.remove();

            // Рендерим в зависимости от типа
            switch (widgetConfig.widget_type) {
                case 'kpi_card':
                    this.renderKPI(widget, data, widgetConfig.config);
                    break;
                case 'funnel_chart':
                    this.renderFunnelChart(widget, data, widgetConfig.config);
                    break;
                case 'bar_chart':
                    this.renderBarChart(widget, data, widgetConfig.config);
                    break;
                case 'line_chart':
                    this.renderLineChart(widget, data, widgetConfig.config);
                    break;
                case 'pie_chart':
                    this.renderPieChart(widget, data, widgetConfig.config);
                    break;
                case 'table':
                    this.renderTable(widget, data, widgetConfig.config);
                    break;
                default:
                    widget.innerHTML += '<div>Неподдерживаемый тип виджета</div>';
            }
        } catch (error) {
            loading.remove();
            const errorDiv = document.createElement('div');
            errorDiv.className = 'widget-error';
            errorDiv.textContent = 'Ошибка загрузки данных: ' + error.message;
            widget.appendChild(errorDiv);
        }

        return widget;
    }

    async fetchWidgetData(dataSource) {
        const params = new URLSearchParams({
            date_from: this.filters.date_from,
            date_to: this.filters.date_to
        });

        const response = await fetch(`/api/analytics/${dataSource}.php?${params}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to fetch data');
        }

        return result.data;
    }

    renderKPI(widget, data, config) {
        widget.classList.add('widget-kpi');

        // Извлекаем значение метрики
        let value = null;

        if (config.metric === 'total_calls') {
            // Для воронки берем первый элемент
            value = Array.isArray(data) ? data[0]?.count : data.total_calls;
        } else if (config.metric === 'success_rate') {
            // Конверсия в успешные (4й этап воронки)
            value = Array.isArray(data) && data[3] ? data[3].conversion_from_previous : 0;
        } else if (config.metric === 'deal_rate') {
            // Конверсия в сделки (5й этап воронки)
            value = Array.isArray(data) && data[4] ? data[4].conversion_from_previous : 0;
        } else if (config.metric === 'hot_deal_rate') {
            // Конверсия в горячие (6й этап воронки)
            value = Array.isArray(data) && data[5] ? data[5].conversion_from_previous : 0;
        } else if (config.metric && config.metric.includes('.')) {
            // Обработка путей типа "correlation.difference"
            value = data;
            const metricPath = config.metric.split('.');
            for (const key of metricPath) {
                if (value && typeof value === 'object') {
                    value = value[key];
                } else {
                    value = null;
                    break;
                }
            }
        } else if (config.metric) {
            // Прямое обращение к полю
            value = data[config.metric];
        } else {
            value = data;
        }

        // Форматирование
        let displayValue = value !== null && value !== undefined ? value : '-';
        if (value !== null && value !== undefined) {
            if (config.format === 'percentage') {
                displayValue = parseFloat(value).toFixed(1) + '%';
            } else if (config.format === 'number') {
                displayValue = parseInt(value).toLocaleString();
            }
        }

        const valueDiv = document.createElement('div');
        valueDiv.className = 'kpi-value';
        valueDiv.textContent = displayValue;
        widget.appendChild(valueDiv);

        if (config.subtitle) {
            const subtitle = document.createElement('div');
            subtitle.className = 'kpi-subtitle';
            subtitle.textContent = config.subtitle;
            widget.appendChild(subtitle);
        }
    }

    renderFunnelChart(widget, data, config) {
        widget.classList.add('widget-chart');

        const canvas = document.createElement('canvas');
        widget.appendChild(canvas);

        const labels = data.map(d => d.stage);
        const values = data.map(d => d.count);
        const percentages = data.map(d => d.percentage);

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Количество',
                    data: values,
                    backgroundColor: '#2196F3',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const index = context.dataIndex;
                                return `${context.parsed.x} (${percentages[index].toFixed(1)}%)`;
                            }
                        }
                    }
                }
            }
        });

        this.charts[widget.id] = chart;
    }

    renderBarChart(widget, data, config) {
        widget.classList.add('widget-chart');

        const canvas = document.createElement('canvas');
        widget.appendChild(canvas);

        const labels = data.map(d => d[config.xAxis]);
        const values = data.map(d => d[config.yAxis]);

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: config.yAxis,
                    data: values,
                    backgroundColor: config.color || '#2196F3',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        this.charts[widget.id] = chart;
    }

    renderLineChart(widget, data, config) {
        widget.classList.add('widget-chart');

        const canvas = document.createElement('canvas');
        widget.appendChild(canvas);

        const labels = data.map(d => d[config.xAxis]);
        const datasets = config.lines.map(line => ({
            label: line.label,
            data: data.map(d => d[line.key]),
            borderColor: line.color,
            backgroundColor: line.color + '20',
            tension: 0.3,
            fill: false
        }));

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        this.charts[widget.id] = chart;
    }

    renderPieChart(widget, data, config) {
        widget.classList.add('widget-chart');

        const canvas = document.createElement('canvas');
        widget.appendChild(canvas);

        const labels = data.map(d => d[config.dataKey]);
        const values = data.map(d => d[config.valueKey]);
        const colors = labels.map(label => config.colors[label] || this.getRandomColor());

        const chart = new Chart(canvas, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        this.charts[widget.id] = chart;
    }

    renderTable(widget, data, config) {
        widget.classList.add('widget-table');

        const table = document.createElement('table');

        // Header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        config.columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = this.formatColumnName(col);
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Body
        const tbody = document.createElement('tbody');
        data.forEach(row => {
            const tr = document.createElement('tr');
            config.columns.forEach(col => {
                const td = document.createElement('td');
                const value = row[col];

                // Форматирование значений
                if (typeof value === 'number') {
                    if (col.includes('rate') || col.includes('compliance')) {
                        td.textContent = value.toFixed(1) + '%';
                    } else {
                        td.textContent = value.toLocaleString();
                    }
                } else {
                    td.textContent = value || '-';
                }

                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        widget.appendChild(table);
    }

    formatColumnName(columnKey) {
        const translations = {
            'manager_name': 'Менеджер',
            'total_calls': 'Всего звонков',
            'success_rate': '% Успешных',
            'deal_rate': '% Сделок',
            'hot_deal_rate': '% Горячих',
            'avg_compliance': 'Avg Compliance'
        };
        return translations[columnKey] || columnKey;
    }

    getRandomColor() {
        const colors = ['#2196F3', '#4CAF50', '#FF5722', '#FFC107', '#9C27B0', '#00BCD4'];
        return colors[Math.floor(Math.random() * colors.length)];
    }

    applyFilters() {
        this.filters.date_from = document.getElementById('date-from').value;
        this.filters.date_to = document.getElementById('date-to').value;

        if (this.currentDashboard) {
            this.renderDashboard();
        }
    }

    resetFilters() {
        const today = new Date();
        const monthAgo = new Date();
        monthAgo.setDate(today.getDate() - 30);

        document.getElementById('date-from').value = monthAgo.toISOString().split('T')[0];
        document.getElementById('date-to').value = today.toISOString().split('T')[0];

        this.applyFilters();
    }

    showError(message) {
        const container = document.getElementById('dashboard-container');
        container.innerHTML = `<div class="widget-error" style="grid-column: 1 / -1;">${message}</div>`;
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new AnalyticsDashboard();
});
