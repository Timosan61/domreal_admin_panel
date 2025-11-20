/**
 * Funnel By Manager Dashboard
 * Дашборд воронки покупателя с разбивкой по менеджерам
 */

(function() {
    'use strict';

    // Chart instance
    let funnelChart = null;

    // Current mode (managers | departments | detailed)
    let currentMode = 'managers';

    /**
     * Generate distinct colors for funnel stages using HSL color space
     * @param {number} count - Number of colors to generate
     * @returns {Array<string>} - Array of hex color strings
     */
    function generateStageColors(count) {
        const colors = [];
        const saturation = 70;
        const lightness = 50;

        for (let i = 0; i < count; i++) {
            const hue = (i * 360 / count) % 360;
            colors.push(hslToHex(hue, saturation, lightness));
        }

        return colors;
    }

    /**
     * Convert HSL to Hex color
     * @param {number} h - Hue (0-360)
     * @param {number} s - Saturation (0-100)
     * @param {number} l - Lightness (0-100)
     * @returns {string} - Hex color string
     */
    function hslToHex(h, s, l) {
        s /= 100;
        l /= 100;

        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs((h / 60) % 2 - 1));
        const m = l - c / 2;

        let r = 0, g = 0, b = 0;

        if (h >= 0 && h < 60) {
            r = c; g = x; b = 0;
        } else if (h >= 60 && h < 120) {
            r = x; g = c; b = 0;
        } else if (h >= 120 && h < 180) {
            r = 0; g = c; b = x;
        } else if (h >= 180 && h < 240) {
            r = 0; g = x; b = c;
        } else if (h >= 240 && h < 300) {
            r = x; g = 0; b = c;
        } else {
            r = c; g = 0; b = x;
        }

        const toHex = (val) => {
            const hex = Math.round((val + m) * 255).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };

        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    /**
     * Initialize funnel dashboard
     */
    function initFunnelDashboard() {
        const chartEl = document.getElementById('funnel-by-manager-chart');

        if (!chartEl) {
            console.warn('Funnel by manager chart container not found');
            return;
        }

        funnelChart = echarts.init(chartEl);

        // Setup mode switcher buttons
        setupModeSwitcher();

        console.log('Funnel by manager dashboard initialized');
    }

    /**
     * Setup mode switcher buttons
     */
    function setupModeSwitcher() {
        const buttons = document.querySelectorAll('[data-funnel-mode]');

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const newMode = this.getAttribute('data-funnel-mode');

                // Update active button
                buttons.forEach(btn => btn.classList.remove('active', 'btn-primary'));
                buttons.forEach(btn => btn.classList.add('btn-outline-secondary'));
                this.classList.remove('btn-outline-secondary');
                this.classList.add('active', 'btn-primary');

                // Update mode and reload
                currentMode = newMode;

                // Reload data with new mode
                if (window.currentFilters) {
                    loadFunnelByManagerData(window.currentFilters);
                }
            });
        });
    }

    /**
     * Load and render funnel data
     * @param {Object} filters - Filter parameters from analytics.js
     */
    async function loadFunnelByManagerData(filters) {
        try {
            const params = new URLSearchParams();
            params.append('date_from', filters.date_from);
            params.append('date_to', filters.date_to);
            params.append('mode', currentMode);

            if (filters.departments && filters.departments.length > 0) {
                params.append('departments', filters.departments.join(','));
            }

            if (filters.managers && filters.managers.length > 0) {
                params.append('managers', filters.managers.join(','));
            }

            const response = await fetch(`/api/analytics/funnel_by_manager.php?${params}`);
            const data = await response.json();

            if (!data.success) {
                console.error('Failed to load funnel data:', data);
                showEmptyState('Ошибка загрузки данных');
                return;
            }

            renderFunnelDashboard(data.data);
        } catch (error) {
            console.error('Error loading funnel by manager data:', error);
            showEmptyState('Ошибка загрузки данных');
        }
    }

    /**
     * Render funnel dashboard (table + chart)
     * @param {Object} data - API response data
     */
    function renderFunnelDashboard(data) {
        if (!data || !data.items || data.items.length === 0) {
            showEmptyState('Нет данных для отображения');
            return;
        }

        // Sort items by total_leads descending (highest first)
        if (data.mode === 'detailed') {
            // For detailed mode, sort departments and managers within departments
            data.items.sort((a, b) => b.total_leads - a.total_leads);
            data.items.forEach(dept => {
                if (dept.managers && Array.isArray(dept.managers)) {
                    dept.managers.sort((a, b) => b.total_leads - a.total_leads);
                }
            });
        } else {
            // For simple modes, just sort items
            data.items.sort((a, b) => b.total_leads - a.total_leads);
        }

        // Render table
        renderFunnelTable(data);

        // Render chart
        renderFunnelChart(data);
    }

    /**
     * Render funnel table
     * @param {Object} data - Dashboard data
     */
    function renderFunnelTable(data) {
        const tableContainer = document.getElementById('funnel-table-container');

        if (!tableContainer) return;

        // Generate colors for funnel stages
        const funnelStages = data.funnel_stages || [];
        const stageColors = generateStageColors(funnelStages.length);

        let html = '<table class="table table-sm table-bordered table-hover" style="min-width: 1400px; margin-bottom: 0;">';

        // Table header
        html += '<thead class="thead-light"><tr>';
        html += `<th class="text-left" style="min-width: 250px; white-space: nowrap;">${data.mode === 'departments' ? 'Отдел' : 'Менеджер'}</th>`;

        // Сводные колонки сразу после имени менеджера
        html += '<th class="text-center font-weight-bold" style="background-color: #e3f2fd; min-width: 90px;">В воронке</th>';
        html += '<th class="text-center font-weight-bold" style="background-color: #fff3e0; min-width: 100px;">Всего звонков</th>';
        html += '<th class="text-center font-weight-bold" style="background-color: #ffebee; min-width: 110px;">Заполнение CRM</th>';

        // Этапы CRM после сводных данных
        funnelStages.forEach((stage, idx) => {
            html += `<th class="text-center" style="background-color: ${stageColors[idx]}20; min-width: 80px;">${stage}</th>`;
        });

        html += '</tr></thead><tbody>';

        // Table rows
        if (data.mode === 'detailed') {
            // Detailed mode: departments with managers
            data.items.forEach(dept => {
                // Department row (bold)
                html += '<tr class="table-primary font-weight-bold">';
                html += `<td>${dept.department}</td>`;

                // Сводные данные сразу после названия отдела
                const deptRatio = dept.total_calls > 0 ? ((dept.total_leads / dept.total_calls) * 100).toFixed(2) : '-';
                const deptRatioColor = deptRatio === '-' ? '' : (deptRatio >= 30 ? '#c8e6c9' : deptRatio >= 10 ? '#fff9c4' : '#ffcdd2');

                html += `<td class="text-center" style="background-color: #e3f2fd;">${dept.total_leads}</td>`;
                html += `<td class="text-center" style="background-color: #fff3e0;">${dept.total_calls || 0}</td>`;
                html += `<td class="text-center" style="background-color: ${deptRatioColor};">${deptRatio === '-' ? '-' : deptRatio + '%'}</td>`;

                // Этапы CRM после сводных данных
                funnelStages.forEach(stage => {
                    const count = dept.stages[stage] || 0;
                    html += `<td class="text-center">${count}</td>`;
                });

                html += '</tr>';

                // Manager rows
                dept.managers.forEach(manager => {
                    html += '<tr>';
                    html += `<td style="padding-left: 30px;">${manager.manager_name}</td>`;

                    // Сводные данные сразу после имени менеджера
                    const managerRatio = manager.total_calls > 0 ? ((manager.total_leads / manager.total_calls) * 100).toFixed(2) : '-';
                    const managerRatioColor = managerRatio === '-' ? '' : (managerRatio >= 30 ? '#c8e6c9' : managerRatio >= 10 ? '#fff9c4' : '#ffcdd2');

                    html += `<td class="text-center" style="background-color: #e3f2fd;">${manager.total_leads}</td>`;
                    html += `<td class="text-center" style="background-color: #fff3e0;">${manager.total_calls || 0}</td>`;
                    html += `<td class="text-center" style="background-color: ${managerRatioColor};">${managerRatio === '-' ? '-' : managerRatio + '%'}</td>`;

                    // Этапы CRM после сводных данных
                    funnelStages.forEach(stage => {
                        const count = manager.stages[stage] || 0;
                        html += `<td class="text-center">${count > 0 ? count : '-'}</td>`;
                    });

                    html += '</tr>';
                });
            });
        } else {
            // Simple mode: managers or departments
            data.items.forEach(item => {
                html += '<tr>';
                html += `<td>${item.manager_name || item.department}</td>`;

                // Сводные данные сразу после имени
                const itemRatio = item.total_calls > 0 ? ((item.total_leads / item.total_calls) * 100).toFixed(2) : '-';
                const itemRatioColor = itemRatio === '-' ? '' : (itemRatio >= 30 ? '#c8e6c9' : itemRatio >= 10 ? '#fff9c4' : '#ffcdd2');

                html += `<td class="text-center font-weight-bold" style="background-color: #e3f2fd;">${item.total_leads}</td>`;
                html += `<td class="text-center font-weight-bold" style="background-color: #fff3e0;">${item.total_calls || 0}</td>`;
                html += `<td class="text-center font-weight-bold" style="background-color: ${itemRatioColor};">${itemRatio === '-' ? '-' : itemRatio + '%'}</td>`;

                // Этапы CRM после сводных данных
                funnelStages.forEach(stage => {
                    const count = item.stages[stage] || 0;
                    html += `<td class="text-center">${count > 0 ? count : '-'}</td>`;
                });

                html += '</tr>';
            });
        }

        // Total row
        html += '<tr class="table-secondary font-weight-bold">';
        html += '<td>ИТОГО</td>';

        // Calculate total_calls sum
        let totalCallsSum = 0;
        data.items.forEach(item => {
            if (data.mode === 'detailed') {
                totalCallsSum += item.total_calls || 0;
            } else {
                totalCallsSum += item.total_calls || 0;
            }
        });

        // Сводные данные сразу после "ИТОГО"
        const totalRatio = totalCallsSum > 0 ? ((data.total_leads / totalCallsSum) * 100).toFixed(2) : '-';
        const totalRatioColor = totalRatio === '-' ? '' : (totalRatio >= 30 ? '#c8e6c9' : totalRatio >= 10 ? '#fff9c4' : '#ffcdd2');

        html += `<td class="text-center" style="background-color: #e3f2fd;">${data.total_leads}</td>`;
        html += `<td class="text-center" style="background-color: #fff3e0;">${totalCallsSum}</td>`;
        html += `<td class="text-center" style="background-color: ${totalRatioColor};">${totalRatio === '-' ? '-' : totalRatio + '%'}</td>`;

        // Этапы CRM после сводных данных
        funnelStages.forEach(stage => {
            const count = data.stages_summary[stage] || 0;
            html += `<td class="text-center">${count}</td>`;
        });

        html += '</tr>';

        html += '</tbody></table>';

        tableContainer.innerHTML = html;
    }

    /**
     * Render funnel stacked bar chart (horizontal bars like other dashboards)
     * @param {Object} data - Dashboard data
     */
    function renderFunnelChart(data) {
        if (!funnelChart) return;

        // Generate colors for funnel stages
        const funnelStages = data.funnel_stages || [];
        const stageColors = generateStageColors(funnelStages.length);

        // Prepare data for horizontal stacked bar chart
        const entityNames = data.items.map(item => {
            const name = item.manager_name || item.department;
            const total = item.total_leads;
            return `${name} (${total})`;
        });

        // Series data: one series per stage
        const series = funnelStages.map((stage, idx) => {
            return {
                name: stage,
                type: 'bar',
                stack: 'total',
                data: data.items.map(item => item.stages[stage] || 0),
                itemStyle: {
                    color: stageColors[idx]
                },
                emphasis: {
                    focus: 'series'
                }
            };
        });

        // Dynamic height based on number of entities
        const calculatedHeight = entityNames.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('funnel-by-manager-chart');

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        funnelChart.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                },
                formatter: function(params) {
                    let tooltip = `<b>${params[0].axisValue}</b><br/>`;

                    let total = 0;
                    params.forEach(item => {
                        if (item.value > 0) {
                            tooltip += `${item.marker} ${item.seriesName}: ${item.value}<br/>`;
                            total += item.value;
                        }
                    });

                    tooltip += `<br/><b>Всего заявок: ${total}</b>`;
                    return tooltip;
                }
            },
            legend: {
                data: funnelStages,
                type: 'scroll',
                orient: 'horizontal',
                left: 'center',
                top: 5,
                itemWidth: 14,
                itemHeight: 14,
                textStyle: {
                    fontSize: 11
                }
            },
            grid: {
                left: '25%',
                right: calculatedHeight > 700 ? '12%' : '10%',
                bottom: '5%',
                top: '8%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Количество заявок',
                axisLabel: {
                    fontSize: 11
                }
            },
            yAxis: {
                type: 'category',
                data: entityNames,
                axisLabel: {
                    interval: 0,
                    fontSize: 10
                }
            },
            series: series
        };

        // Add DataZoom if too many items
        if (calculatedHeight > 700) {
            const visiblePercent = (700 / calculatedHeight * 100);
            option.dataZoom = [
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10,
                    width: 20,
                    start: 0,
                    end: visiblePercent,
                    textStyle: {
                        fontSize: 10
                    },
                    handleSize: '80%',
                    showDetail: false,
                    zoomLock: true,
                    fillerColor: 'rgba(33, 150, 243, 0.15)',
                    borderColor: '#2196F3'
                },
                {
                    type: 'inside',
                    yAxisIndex: 0,
                    start: 0,
                    end: visiblePercent,
                    zoomOnMouseWheel: false,
                    moveOnMouseWheel: true,
                    zoomLock: true
                }
            ];
        }

        funnelChart.setOption(option, true);
    }

    /**
     * Show empty state in chart
     * @param {string} message - Message to display
     */
    function showEmptyState(message) {
        if (funnelChart) {
            funnelChart.setOption({
                title: {
                    text: message,
                    left: 'center',
                    top: 'middle',
                    textStyle: {
                        color: '#999',
                        fontSize: 14
                    }
                },
                series: []
            }, true);
        }

        const tableContainer = document.getElementById('funnel-table-container');
        if (tableContainer) {
            tableContainer.innerHTML = `<div class="alert alert-info">${message}</div>`;
        }
    }

    /**
     * Resize chart
     */
    function resizeFunnelChart() {
        if (funnelChart) {
            funnelChart.resize();
        }
    }

    // Export functions to global scope
    window.funnelByManagerDashboard = {
        init: initFunnelDashboard,
        load: loadFunnelByManagerData,
        resize: resizeFunnelChart
    };

    // Auto-initialize if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFunnelDashboard);
    } else {
        // DOM already loaded, initialize immediately
        setTimeout(initFunnelDashboard, 100);
    }

})();
