/**
 * Communication Metrics Charts
 * Графики метрик коммуникации: перебивания и Talk-to-Listen
 */

(function() {
    'use strict';

    // Chart instances
    let communicationCharts = {
        interruptions: null,
        talkListen: null
    };

    /**
     * Initialize communication charts
     */
    function initCommunicationCharts() {
        const interruptionsEl = document.getElementById('interruptions-chart');
        const talkListenEl = document.getElementById('talk-listen-chart');

        if (!interruptionsEl || !talkListenEl) {
            console.warn('Communication chart containers not found');
            return;
        }

        communicationCharts.interruptions = echarts.init(interruptionsEl);
        communicationCharts.talkListen = echarts.init(talkListenEl);

        console.log('Communication charts initialized');
    }

    /**
     * Load and render interruptions chart
     * @param {Object} filters - Filter parameters
     */
    async function loadInterruptionsChart(filters) {
        try {
            const queryString = buildQueryString(filters);
            const response = await fetchWithRetry(`/api/analytics/interruptions.php?${queryString}`);
            const data = await response.json();

            if (!data.success) {
                console.error('Failed to load interruptions data:', data);
                showEmptyState(communicationCharts.interruptions, 'Нет данных о перебиваниях');
                return;
            }

            renderInterruptionsChart(data.data);
        } catch (error) {
            console.error('Error loading interruptions chart:', error);
            showEmptyState(communicationCharts.interruptions, 'Ошибка загрузки данных');
        }
    }

    /**
     * Load and render Talk-to-Listen chart
     * @param {Object} filters - Filter parameters
     */
    async function loadTalkListenChart(filters) {
        try {
            const queryString = buildQueryString(filters);
            const response = await fetchWithRetry(`/api/analytics/talk_listen.php?${queryString}`);
            const data = await response.json();

            if (!data.success) {
                console.error('Failed to load talk-listen data:', data);
                showEmptyState(communicationCharts.talkListen, 'Нет данных о соотношении речи');
                return;
            }

            renderTalkListenChart(data.data);
        } catch (error) {
            console.error('Error loading talk-listen chart:', error);
            showEmptyState(communicationCharts.talkListen, 'Ошибка загрузки данных');
        }
    }

    /**
     * Render interruptions chart
     * @param {Object} data - Chart data
     */
    function renderInterruptionsChart(data) {
        if (!data || !data.managers || data.managers.length === 0) {
            showEmptyState(communicationCharts.interruptions, 'Нет данных для отображения');
            return;
        }

        // РЕВЕРС - лучшие вверху (низкий процент перебиваний)
        const indices = data.managers.map((_, idx) => idx);
        indices.sort((a, b) => data.interruption_rate[a] - data.interruption_rate[b]);

        const managers = indices.map(idx => {
            const totalCalls = data.total_calls[idx];
            const rate = data.interruption_rate[idx];
            return `${data.managers[idx]} (${totalCalls} зв., ${rate}%)`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('interruptions-chart');

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        communicationCharts.interruptions.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: function(params) {
                    const idx = indices[params[0].dataIndex];
                    const managerName = data.managers[idx];
                    const totalCalls = data.total_calls[idx];
                    const rate = data.interruption_rate[idx];
                    const interruptions = data.total_interruptions[idx];
                    const avgPerCall = data.avg_per_call[idx];

                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/>`;
                    tooltip += `Процент перебиваний: <b>${rate}%</b><br/>`;
                    tooltip += `Всего перебиваний: ${interruptions}<br/>`;
                    tooltip += `В среднем на звонок: ${avgPerCall.toFixed(1)}<br/><br/>`;

                    if (rate < 20) {
                        tooltip += '<span style="color: #28a745;">✅ Отличное активное слушание</span>';
                    } else if (rate < 30) {
                        tooltip += '<span style="color: #81c784;">✅ Хороший баланс</span>';
                    } else if (rate < 50) {
                        tooltip += '<span style="color: #ffa726;">⚠️ Частые перебивания</span>';
                    } else {
                        tooltip += '<span style="color: #e53935;">⚠️ Критично высокий уровень</span>';
                    }

                    return tooltip;
                }
            },
            grid: {
                left: '25%',
                right: calculatedHeight > 700 ? '12%' : '10%',
                bottom: '5%',
                top: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Процент перебиваний (%)',
                min: 0,
                max: 100,
                axisLabel: {
                    formatter: '{value}%'
                }
            },
            yAxis: {
                type: 'category',
                data: managers,
                axisLabel: {
                    interval: 0,
                    fontSize: 10
                }
            },
            series: [
                {
                    name: 'Процент перебиваний',
                    type: 'bar',
                    data: indices.map(idx => data.interruption_rate[idx]),
                    itemStyle: {
                        color: function(params) {
                            const value = params.value;
                            if (value < 20) {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#28a745' },
                                        { offset: 1, color: '#66bb6a' }
                                    ]
                                };
                            } else if (value < 30) {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#81c784' },
                                        { offset: 1, color: '#aed581' }
                                    ]
                                };
                            } else if (value < 50) {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#ffa726' },
                                        { offset: 1, color: '#ffb74d' }
                                    ]
                                };
                            } else {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#e53935' },
                                        { offset: 1, color: '#ef5350' }
                                    ]
                                };
                            }
                        }
                    },
                    label: {
                        show: true,
                        position: 'right',
                        formatter: '{c}%',
                        fontSize: 10
                    }
                }
            ]
        };

        // DataZoom если много данных
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

        communicationCharts.interruptions.setOption(option);

        // Drilldown при клике
        communicationCharts.interruptions.off('click');
        communicationCharts.interruptions.on('click', function(params) {
            if (params.componentType === 'series') {
                const idx = indices[params.dataIndex];
                const managerName = data.managers[idx];
                if (window.openCallsWithFilters) {
                    window.openCallsWithFilters(null, null, managerName);
                }
            }
        });
    }

    /**
     * Render Talk-to-Listen chart
     * @param {Object} data - Chart data
     */
    function renderTalkListenChart(data) {
        if (!data || !data.managers || data.managers.length === 0) {
            showEmptyState(communicationCharts.talkListen, 'Нет данных для отображения');
            return;
        }

        // РЕВЕРС - лучшие вверху (соотношение ближе к 1.0)
        const indices = data.managers.map((_, idx) => idx);
        indices.sort((a, b) => {
            const diffA = Math.abs(data.ratio[a] - 1.0);
            const diffB = Math.abs(data.ratio[b] - 1.0);
            return diffA - diffB;
        });

        const managers = indices.map(idx => {
            const totalCalls = data.total_calls[idx];
            const ratio = data.ratio[idx];
            return `${data.managers[idx]} (${totalCalls} зв., ${ratio.toFixed(2)})`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('talk-listen-chart');

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        communicationCharts.talkListen.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: function(params) {
                    const idx = indices[params[0].dataIndex];
                    const managerName = data.managers[idx];
                    const totalCalls = data.total_calls[idx];
                    const ratio = data.ratio[idx];
                    const managerTime = data.manager_time[idx];
                    const clientTime = data.client_time[idx];

                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/>`;
                    tooltip += `Соотношение: <b>${ratio.toFixed(2)}</b><br/>`;
                    tooltip += `Время менеджера: ${formatTime(managerTime)}<br/>`;
                    tooltip += `Время клиента: ${formatTime(clientTime)}<br/><br/>`;

                    if (ratio >= 0.5 && ratio <= 1.5) {
                        tooltip += '<span style="color: #28a745;">✅ Сбалансированный диалог</span>';
                    } else if (ratio < 0.5) {
                        tooltip += '<span style="color: #2196F3;">✅ Больше слушает (консультация)</span>';
                    } else if (ratio < 2.0) {
                        tooltip += '<span style="color: #ffa726;">⚡ Менеджер доминирует</span>';
                    } else {
                        tooltip += '<span style="color: #e53935;">⚠️ Монолог вместо диалога</span>';
                    }

                    return tooltip;
                }
            },
            grid: {
                left: '25%',
                right: calculatedHeight > 700 ? '12%' : '10%',
                bottom: '5%',
                top: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Соотношение (Менеджер/Клиент)',
                min: 0,
                max: 4,
                axisLabel: {
                    formatter: '{value}'
                }
            },
            yAxis: {
                type: 'category',
                data: managers,
                axisLabel: {
                    interval: 0,
                    fontSize: 10
                }
            },
            series: [
                {
                    name: 'Talk-to-Listen',
                    type: 'bar',
                    data: indices.map(idx => data.ratio[idx]),
                    itemStyle: {
                        color: function(params) {
                            const value = params.value;
                            if (value >= 0.5 && value <= 1.5) {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#28a745' },
                                        { offset: 1, color: '#66bb6a' }
                                    ]
                                };
                            } else if (value < 0.5) {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#2196F3' },
                                        { offset: 1, color: '#42A5F5' }
                                    ]
                                };
                            } else if (value < 2.0) {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#ffa726' },
                                        { offset: 1, color: '#ffb74d' }
                                    ]
                                };
                            } else {
                                return {
                                    type: 'linear',
                                    x: 0, y: 0, x2: 1, y2: 0,
                                    colorStops: [
                                        { offset: 0, color: '#e53935' },
                                        { offset: 1, color: '#ef5350' }
                                    ]
                                };
                            }
                        }
                    },
                    label: {
                        show: true,
                        position: 'right',
                        formatter: function(params) {
                            return params.value.toFixed(2);
                        },
                        fontSize: 10
                    },
                    markLine: {
                        data: [
                            { xAxis: 1.0, label: { formatter: 'Идеал (1.0)', position: 'end' } }
                        ],
                        lineStyle: {
                            color: '#28a745',
                            type: 'dashed',
                            width: 2
                        }
                    }
                }
            ]
        };

        // DataZoom если много данных
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
                    zoomLock: true,
                    fillerColor: 'rgba(255, 152, 0, 0.15)',
                    borderColor: '#FF9800'
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

        communicationCharts.talkListen.setOption(option);

        // Drilldown при клике
        communicationCharts.talkListen.off('click');
        communicationCharts.talkListen.on('click', function(params) {
            if (params.componentType === 'series') {
                const idx = indices[params.dataIndex];
                const managerName = data.managers[idx];
                if (window.openCallsWithFilters) {
                    window.openCallsWithFilters(null, null, managerName);
                }
            }
        });
    }

    /**
     * Show empty state in chart
     */
    function showEmptyState(chart, message) {
        if (!chart) return;
        chart.setOption({
            title: {
                text: message || 'Нет данных для отображения',
                left: 'center',
                top: 'middle',
                textStyle: {
                    color: '#999',
                    fontSize: 14
                }
            }
        });
    }

    /**
     * Build query string from filters
     */
    function buildQueryString(filters) {
        if (!filters) return '';

        const params = new URLSearchParams();

        if (filters.date_from) params.append('date_from', filters.date_from);
        if (filters.date_to) params.append('date_to', filters.date_to);

        if (filters.departments && filters.departments.length > 0) {
            params.append('departments', filters.departments.join(','));
        }

        if (filters.managers && filters.managers.length > 0) {
            params.append('managers', filters.managers.join(','));
        }

        if (filters.crm_stages && filters.crm_stages.length > 0) {
            const crmStagesValues = filters.crm_stages.map(stage => stage.replace(' → ', ':'));
            params.append('crm_stages', crmStagesValues.join(','));
        }

        return params.toString();
    }

    /**
     * Format time in seconds to readable format
     */
    function formatTime(seconds) {
        if (!seconds || seconds < 0) return '0 сек';

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        const parts = [];
        if (hours > 0) parts.push(`${hours} ч`);
        if (minutes > 0) parts.push(`${minutes} мин`);
        if (secs > 0 || parts.length === 0) parts.push(`${secs} сек`);

        return parts.join(' ');
    }

    /**
     * Resize charts on window resize
     */
    function resizeCommunicationCharts() {
        Object.values(communicationCharts).forEach(chart => {
            if (chart) chart.resize();
        });
    }

    // Export functions to global scope
    window.communicationCharts = {
        init: initCommunicationCharts,
        loadInterruptions: loadInterruptionsChart,
        loadTalkListen: loadTalkListenChart,
        resize: resizeCommunicationCharts
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initCommunicationCharts();

            // Load charts with current filters from analytics page
            if (window.currentFilters) {
                loadInterruptionsChart(window.currentFilters);
                loadTalkListenChart(window.currentFilters);
            }
        });
    } else {
        initCommunicationCharts();

        if (window.currentFilters) {
            loadInterruptionsChart(window.currentFilters);
            loadTalkListenChart(window.currentFilters);
        }
    }

    // Auto-resize on window resize
    if (window.ResizeObserver) {
        const chartContainers = document.querySelectorAll('#interruptions-chart, #talk-listen-chart');
        const resizeObserver = new ResizeObserver(function() {
            resizeCommunicationCharts();
        });
        chartContainers.forEach(container => {
            if (container) resizeObserver.observe(container);
        });
    } else {
        window.addEventListener('resize', function() {
            resizeCommunicationCharts();
        });
    }

})();
