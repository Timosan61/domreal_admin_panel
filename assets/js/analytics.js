/**
 * Аналитика - ECharts Dashboard
 * Управление графиками и фильтрами
 */

(function() {
    'use strict';

    // Global chart instances
    let charts = {
        funnel: null,
        dynamics: null,
        departments: null,
        managers: null,
        scriptQuality: null
    };

    // Current filters
    let currentFilters = {
        date_from: null,
        date_to: null,
        departments: [],
        managers: []
    };

    // Multi-select state
    let multiSelectData = {
        departments: [],
        managers: []
    };

    /**
     * Initialize analytics page
     */
    function init() {
        initDateFilters();
        initMultiSelect();
        initCharts();
        loadFilterOptions();
        loadDashboardData();

        // Event listeners
        document.getElementById('apply-filters').addEventListener('click', applyFilters);
        document.getElementById('reset-filters').addEventListener('click', resetFilters);

        // Initialize KPI click handlers for drilldown
        initKPIClickHandlers();

        // Auto-resize charts on window resize and zoom
        const debouncedResize = debounce(resizeCharts, 250);
        window.addEventListener('resize', debouncedResize);

        // Additional listener for zoom events
        let lastWidth = window.innerWidth;
        let lastHeight = window.innerHeight;

        window.addEventListener('resize', function() {
            const widthChanged = Math.abs(window.innerWidth - lastWidth) > 10;
            const heightChanged = Math.abs(window.innerHeight - lastHeight) > 10;

            if (widthChanged || heightChanged) {
                lastWidth = window.innerWidth;
                lastHeight = window.innerHeight;
                debouncedResize();
            }
        });

        // Force resize after a short delay to ensure charts are properly sized
        setTimeout(() => {
            resizeCharts();
        }, 500);
    }

    /**
     * Initialize date filters with default values
     */
    function initDateFilters() {
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');

        currentFilters.date_from = dateFrom.value;
        currentFilters.date_to = dateTo.value;
    }

    /**
     * Initialize multi-select dropdowns
     */
    function initMultiSelect() {
        // Departments multi-select
        const deptDisplay = document.getElementById('departments-display');
        const deptDropdown = document.getElementById('departments-dropdown');

        deptDisplay.addEventListener('click', function() {
            const isOpening = !deptDropdown.classList.contains('active');
            deptDropdown.classList.toggle('active');
            document.getElementById('managers-dropdown').classList.remove('active');

            // Очистка поиска при открытии
            if (isOpening) {
                const searchField = document.getElementById('departments-search');
                searchField.value = '';
                filterOptions('departments', '');
            }
        });

        // Managers multi-select
        const mgrDisplay = document.getElementById('managers-display');
        const mgrDropdown = document.getElementById('managers-dropdown');

        mgrDisplay.addEventListener('click', function() {
            const isOpening = !mgrDropdown.classList.contains('active');
            mgrDropdown.classList.toggle('active');
            document.getElementById('departments-dropdown').classList.remove('active');

            // Очистка поиска при открытии
            if (isOpening) {
                const searchField = document.getElementById('managers-search');
                searchField.value = '';
                filterOptions('managers', '');
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.multi-select-wrapper')) {
                deptDropdown.classList.remove('active');
                mgrDropdown.classList.remove('active');
            }
        });

        // Department buttons
        document.getElementById('departments-select-all').addEventListener('click', function(e) {
            e.stopPropagation();
            selectAllOptions('departments');
        });

        document.getElementById('departments-clear').addEventListener('click', function(e) {
            e.stopPropagation();
            clearAllOptions('departments');
        });

        // Manager buttons
        document.getElementById('managers-select-all').addEventListener('click', function(e) {
            e.stopPropagation();
            selectAllOptions('managers');
        });

        document.getElementById('managers-clear').addEventListener('click', function(e) {
            e.stopPropagation();
            clearAllOptions('managers');
        });

        // Search fields
        document.getElementById('departments-search').addEventListener('input', function(e) {
            e.stopPropagation();
            filterOptions('departments', e.target.value);
        });

        document.getElementById('departments-search').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.getElementById('managers-search').addEventListener('input', function(e) {
            e.stopPropagation();
            filterOptions('managers', e.target.value);
        });

        document.getElementById('managers-search').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Load departments and managers for filters
     */
    async function loadFilterOptions() {
        try {
            // Load departments
            const deptResponse = await fetch('/api/filters/departments.php');
            const deptData = await deptResponse.json();
            console.log('Departments filter data:', deptData);

            if (deptData.success) {
                multiSelectData.departments = deptData.data;
                renderMultiSelectOptions('departments', deptData.data);
            }

            // Load managers
            const mgrResponse = await fetch('/api/filters/managers.php');
            const mgrData = await mgrResponse.json();
            console.log('Managers filter data:', mgrData);

            if (mgrData.success) {
                multiSelectData.managers = mgrData.data;
                renderMultiSelectOptions('managers', mgrData.data);
            }
        } catch (error) {
            console.error('Failed to load filter options:', error);
        }
    }

    /**
     * Render multi-select options
     */
    function renderMultiSelectOptions(type, options) {
        const optionsContainer = document.getElementById(`${type}-options`);
        optionsContainer.innerHTML = '';

        options.forEach(option => {
            const div = document.createElement('div');
            div.className = 'multi-select-option';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = option;
            checkbox.id = `${type}-${option}`;

            checkbox.addEventListener('change', function() {
                updateMultiSelectDisplay(type);
            });

            const label = document.createElement('label');
            label.htmlFor = checkbox.id;
            label.textContent = option;
            label.style.cursor = 'pointer';
            label.style.flex = '1';
            label.style.whiteSpace = 'normal';
            label.style.wordBreak = 'break-word';
            label.style.lineHeight = '1.5';

            div.appendChild(checkbox);
            div.appendChild(label);
            optionsContainer.appendChild(div);
        });
    }

    /**
     * Update multi-select display text
     */
    function updateMultiSelectDisplay(type) {
        const optionsContainer = document.getElementById(`${type}-options`);
        const display = document.getElementById(`${type}-display`).querySelector('span');

        const checked = optionsContainer.querySelectorAll('input[type="checkbox"]:checked');
        const values = Array.from(checked).map(cb => cb.value);

        if (values.length === 0) {
            display.textContent = type === 'departments' ? 'Все отделы' : 'Все менеджеры';
        } else if (values.length === 1) {
            display.textContent = values[0];
        } else {
            display.textContent = `Выбрано: ${values.length}`;
        }
    }

    /**
     * Get selected values from multi-select
     */
    function getMultiSelectValues(type) {
        const optionsContainer = document.getElementById(`${type}-options`);
        const checked = optionsContainer.querySelectorAll('input[type="checkbox"]:checked');
        return Array.from(checked).map(cb => cb.value);
    }

    /**
     * Select all options in multi-select (only visible ones)
     */
    function selectAllOptions(type) {
        const optionsContainer = document.getElementById(`${type}-options`);
        const options = optionsContainer.querySelectorAll('.multi-select-option');

        options.forEach(option => {
            // Выбираем только видимые опции
            if (option.style.display !== 'none') {
                const checkbox = option.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
            }
        });

        updateMultiSelectDisplay(type);
    }

    /**
     * Clear all options in multi-select
     */
    function clearAllOptions(type) {
        const optionsContainer = document.getElementById(`${type}-options`);
        const checkboxes = optionsContainer.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
        updateMultiSelectDisplay(type);
    }

    /**
     * Filter options by search term
     */
    function filterOptions(type, searchTerm) {
        const optionsContainer = document.getElementById(`${type}-options`);
        const options = optionsContainer.querySelectorAll('.multi-select-option');
        const term = searchTerm.toLowerCase();

        options.forEach(option => {
            const label = option.querySelector('label');
            const text = label.textContent.toLowerCase();
            const matches = text.includes(term);
            option.style.display = matches ? 'flex' : 'none';
        });
    }

    /**
     * Initialize all ECharts instances
     */
    function initCharts() {
        charts.funnel = echarts.init(document.getElementById('funnel-chart'));
        charts.dynamics = echarts.init(document.getElementById('dynamics-chart'));
        charts.departments = echarts.init(document.getElementById('departments-chart'));
        charts.managers = echarts.init(document.getElementById('managers-chart'));
        charts.scriptQuality = echarts.init(document.getElementById('script-quality-chart'));

        // Use ResizeObserver for better resize detection
        if (window.ResizeObserver) {
            const chartContainers = document.querySelectorAll('.chart-canvas');
            const resizeObserver = new ResizeObserver(debounce(function(entries) {
                resizeCharts();
            }, 100));

            chartContainers.forEach(container => {
                resizeObserver.observe(container);
            });
        }
    }

    /**
     * Resize all charts
     */
    function resizeCharts() {
        Object.values(charts).forEach(chart => {
            if (chart) chart.resize();
        });
    }

    /**
     * Debounce function to limit resize calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Show loading overlay
     */
    function showLoading() {
        document.getElementById('loading-overlay').classList.add('active');
    }

    /**
     * Hide loading overlay
     */
    function hideLoading() {
        document.getElementById('loading-overlay').classList.remove('active');
    }

    /**
     * Apply filters and reload data
     */
    function applyFilters() {
        currentFilters.date_from = document.getElementById('date_from').value;
        currentFilters.date_to = document.getElementById('date_to').value;
        currentFilters.departments = getMultiSelectValues('departments');
        currentFilters.managers = getMultiSelectValues('managers');

        loadDashboardData();
    }

    /**
     * Reset filters to default
     */
    function resetFilters() {
        // Reset dates
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        dateFrom.value = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        dateTo.value = new Date().toISOString().split('T')[0];

        // Reset multi-selects
        document.querySelectorAll('.multi-select-dropdown input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        updateMultiSelectDisplay('departments');
        updateMultiSelectDisplay('managers');

        // Apply reset
        applyFilters();
    }

    /**
     * Build query string from filters
     */
    function buildQueryString() {
        const params = new URLSearchParams();
        params.append('date_from', currentFilters.date_from);
        params.append('date_to', currentFilters.date_to);

        if (currentFilters.departments.length > 0) {
            params.append('departments', currentFilters.departments.join(','));
        }

        if (currentFilters.managers.length > 0) {
            params.append('managers', currentFilters.managers.join(','));
        }

        return params.toString();
    }

    /**
     * Load all dashboard data
     */
    async function loadDashboardData() {
        showLoading();

        try {
            const queryString = buildQueryString();

            // Load all data in parallel
            console.log('Loading dashboard data with queryString:', queryString);

            const [kpiData, funnelData, dynamicsData, departmentsData, managersData, scriptData] = await Promise.all([
                fetch(`/api/analytics/kpi.php?${queryString}`).then(r => r.json()),
                fetch(`/api/analytics/funnel.php?${queryString}`).then(r => r.json()),
                fetch(`/api/analytics/dynamics.php?${queryString}`).then(r => r.json()),
                fetch(`/api/analytics/departments.php?${queryString}`).then(r => r.json()),
                fetch(`/api/analytics/managers.php?${queryString}`).then(r => r.json()),
                fetch(`/api/analytics/script_quality.php?${queryString}`).then(r => r.json())
            ]);

            console.log('All API responses:', {
                kpiData,
                funnelData,
                dynamicsData,
                departmentsData,
                managersData,
                scriptData
            });

            // Update KPI cards
            if (kpiData.success) {
                updateKPICards(kpiData.data);
            }

            // Update charts
            if (funnelData.success) {
                updateFunnelChart(funnelData.data);
            }

            if (dynamicsData.success) {
                updateDynamicsChart(dynamicsData.data);
            }

            if (departmentsData.success) {
                updateDepartmentsChart(departmentsData.data);
            } else {
                console.error('Departments API error:', departmentsData);
            }

            if (managersData.success) {
                updateManagersChart(managersData.data);
            } else {
                console.error('Managers API error:', managersData);
            }

            if (scriptData.success) {
                updateScriptQualityChart(scriptData.data);
            } else {
                console.error('Script quality API error:', scriptData);
            }

        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            alert('Ошибка загрузки данных аналитики');
        } finally {
            hideLoading();
        }
    }

    /**
     * Update KPI cards
     */
    function updateKPICards(data) {
        document.getElementById('kpi-total-calls').textContent = data.total_calls.toLocaleString();
        document.getElementById('kpi-analyzed-calls').textContent = data.analyzed_calls.toLocaleString();
        document.getElementById('kpi-successful-calls').textContent = data.showing_scheduled.toLocaleString();
        document.getElementById('kpi-conversion-rate').textContent = data.showing_completed.toLocaleString();
        document.getElementById('kpi-first-calls').textContent = data.first_calls.toLocaleString();
        document.getElementById('kpi-script-score').textContent = (data.avg_script_score * 100).toFixed(0) + '%';
    }

    /**
     * Initialize KPI click handlers for drilldown
     */
    function initKPIClickHandlers() {
        // Показ назначен
        const successfulCallsCard = document.getElementById('kpi-successful-calls');
        if (successfulCallsCard) {
            successfulCallsCard.parentElement.style.cursor = 'pointer';
            successfulCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters('показ назначен');
            });
        }

        // Показ состоялся
        const conversionRateCard = document.getElementById('kpi-conversion-rate');
        if (conversionRateCard) {
            conversionRateCard.parentElement.style.cursor = 'pointer';
            conversionRateCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters('показ состоялся');
            });
        }

        // Первые звонки
        const firstCallsCard = document.getElementById('kpi-first-calls');
        if (firstCallsCard) {
            firstCallsCard.parentElement.style.cursor = 'pointer';
            firstCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters(null, 'first_call');
            });
        }

        // Всего звонков
        const totalCallsCard = document.getElementById('kpi-total-calls');
        if (totalCallsCard) {
            totalCallsCard.parentElement.style.cursor = 'pointer';
            totalCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters();
            });
        }

        // Проанализировано
        const analyzedCallsCard = document.getElementById('kpi-analyzed-calls');
        if (analyzedCallsCard) {
            analyzedCallsCard.parentElement.style.cursor = 'pointer';
            analyzedCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters();
            });
        }
    }

    /**
     * Open calls page with filters from analytics
     */
    function openCallsWithFilters(callResult = null, callType = null) {
        const params = new URLSearchParams();

        // Передаем фильтры из аналитики
        params.set('date_from', currentFilters.date_from);
        params.set('date_to', currentFilters.date_to);

        if (currentFilters.departments.length > 0) {
            params.set('departments', currentFilters.departments.join(','));
        }

        if (currentFilters.managers.length > 0) {
            params.set('managers', currentFilters.managers.join(','));
        }

        if (callResult) {
            params.set('call_results', callResult);
        }

        if (callType) {
            params.set('call_type', callType);
        }

        // Флаг что пришли из аналитики
        params.set('from_analytics', '1');

        console.log('Drilldown to calls page with filters:', params.toString());
        window.location.href = `/index_new.php?${params}`;
    }

    /**
     * Update funnel chart
     */
    function updateFunnelChart(data) {
        console.log('updateFunnelChart data:', data);

        if (!data || data.length === 0) {
            console.warn('No funnel data');
            return;
        }

        // Calculate max value for proper proportions
        const maxValue = Math.max(...data.map(item => item.value));

        // Цвета в стиле как на картинке (синий, фиолетовый, красный, оранжевый, желтый, зеленый)
        const colors = [
            { start: '#3f51b5', end: '#2c3e8f' },  // Синий
            { start: '#9c27b0', end: '#6a1b7f' },  // Фиолетовый
            { start: '#e53935', end: '#b71c1c' },  // Красный
            { start: '#ff6f00', end: '#e65100' },  // Оранжевый
            { start: '#fdd835', end: '#f9a825' },  // Желтый
            { start: '#7cb342', end: '#558b2f' }   // Зеленый
        ];

        const option = {
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    const percent = ((params.value / maxValue) * 100).toFixed(1);
                    return `${params.name}<br/>Количество: ${params.value.toLocaleString()}<br/>От всех: ${percent}%`;
                }
            },
            series: [{
                type: 'funnel',
                left: '10%',
                top: 60,
                bottom: 60,
                width: '80%',
                min: 0,
                minSize: '0%',
                maxSize: '100%',
                sort: 'descending',
                gap: 4,  // Увеличен промежуток между слоями
                label: {
                    show: true,
                    position: 'inside',
                    formatter: function(params) {
                        // Показываем только значение большим шрифтом
                        return params.value.toLocaleString();
                    },
                    fontSize: 20,
                    fontWeight: 'bold',
                    color: '#fff'
                },
                labelLine: {
                    show: false
                },
                itemStyle: {
                    borderColor: '#fff',
                    borderWidth: 3  // Увеличена белая граница
                },
                emphasis: {
                    label: {
                        fontSize: 24,
                        fontWeight: 'bold'
                    }
                },
                data: data.map((item, index) => ({
                    value: item.value,
                    name: item.name,
                    itemStyle: {
                        // Градиентная заливка
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 0,
                            y2: 1,
                            colorStops: [
                                { offset: 0, color: colors[index % colors.length].start },
                                { offset: 1, color: colors[index % colors.length].end }
                            ]
                        }
                    }
                }))
            }]
        };

        charts.funnel.setOption(option);
    }

    /**
     * Update dynamics chart
     */
    function updateDynamicsChart(data) {
        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'cross'
                }
            },
            legend: {
                data: ['Всего звонков', 'Проанализировано', 'Показ назначен', 'Показ состоялся']
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: data.dates
            },
            yAxis: {
                type: 'value',
                name: 'Количество звонков'
            },
            series: [
                {
                    name: 'Всего звонков',
                    type: 'line',
                    data: data.total_calls,
                    itemStyle: { color: '#5470c6' }
                },
                {
                    name: 'Проанализировано',
                    type: 'line',
                    data: data.analyzed_calls,
                    itemStyle: { color: '#91cc75' }
                },
                {
                    name: 'Показ назначен',
                    type: 'bar',
                    data: data.showing_scheduled,
                    itemStyle: { color: '#fac858' }
                },
                {
                    name: 'Показ состоялся',
                    type: 'bar',
                    data: data.showing_completed,
                    itemStyle: { color: '#ee6666' }
                }
            ]
        };

        charts.dynamics.setOption(option);
    }

    /**
     * Update departments chart
     */
    function updateDepartmentsChart(data) {
        console.log('updateDepartmentsChart data:', data);

        if (!data || !data.departments || data.departments.length === 0) {
            console.warn('No departments data');
            // Show empty state
            charts.departments.setOption({
                title: {
                    text: 'Нет данных для отображения',
                    left: 'center',
                    top: 'middle',
                    textStyle: {
                        color: '#999',
                        fontSize: 14
                    }
                }
            });
            return;
        }

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            legend: {
                data: ['Всего звонков', 'Договорились о показе', 'Показ зафиксирован', 'Средний % скрипта']
            },
            grid: {
                left: '15%',
                right: '10%',
                bottom: '3%',
                containLabel: true
            },
            xAxis: [
                {
                    type: 'value',
                    name: 'Количество',
                    position: 'bottom'
                },
                {
                    type: 'value',
                    name: '% скрипта',
                    position: 'top',
                    max: 100,
                    axisLabel: {
                        formatter: '{value}%'
                    }
                }
            ],
            yAxis: {
                type: 'category',
                data: data.departments,
                axisLabel: {
                    interval: 0,
                    fontSize: 11
                }
            },
            series: [
                {
                    name: 'Всего звонков',
                    type: 'bar',
                    data: data.total_calls,
                    itemStyle: { color: '#5470c6' }
                },
                {
                    name: 'Договорились о показе',
                    type: 'bar',
                    data: data.showing_scheduled,
                    itemStyle: { color: '#fac858' }
                },
                {
                    name: 'Показ зафиксирован',
                    type: 'bar',
                    data: data.showing_completed,
                    itemStyle: { color: '#91cc75' }
                },
                {
                    name: 'Средний % скрипта',
                    type: 'line',
                    xAxisIndex: 1,
                    data: data.script_scores,
                    itemStyle: { color: '#ee6666' },
                    lineStyle: { width: 3 },
                    symbolSize: 8,
                    label: {
                        show: true,
                        position: 'right',
                        formatter: '{c}%',
                        fontSize: 10
                    }
                }
            ]
        };

        charts.departments.setOption(option);
    }

    /**
     * Update managers chart
     */
    function updateManagersChart(data) {
        console.log('updateManagersChart data:', data);

        if (!data || !data.managers || data.managers.length === 0) {
            console.warn('No managers data');
            // Show empty state
            charts.managers.setOption({
                title: {
                    text: 'Нет данных для отображения',
                    left: 'center',
                    top: 'middle',
                    textStyle: {
                        color: '#999',
                        fontSize: 14
                    }
                }
            });
            return;
        }

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            legend: {
                data: ['Всего звонков', 'Договорились о показе', 'Показ зафиксирован', 'Средний % скрипта']
            },
            grid: {
                left: '20%',
                right: '10%',
                bottom: '3%',
                containLabel: true
            },
            xAxis: [
                {
                    type: 'value',
                    name: 'Количество',
                    position: 'bottom'
                },
                {
                    type: 'value',
                    name: '% скрипта',
                    position: 'top',
                    max: 100,
                    axisLabel: {
                        formatter: '{value}%'
                    }
                }
            ],
            yAxis: {
                type: 'category',
                data: data.managers,
                axisLabel: {
                    interval: 0,
                    fontSize: 11
                }
            },
            series: [
                {
                    name: 'Всего звонков',
                    type: 'bar',
                    data: data.total_calls,
                    itemStyle: { color: '#5470c6' }
                },
                {
                    name: 'Договорились о показе',
                    type: 'bar',
                    data: data.showing_scheduled,
                    itemStyle: { color: '#fac858' }
                },
                {
                    name: 'Показ зафиксирован',
                    type: 'bar',
                    data: data.showing_completed,
                    itemStyle: { color: '#91cc75' }
                },
                {
                    name: 'Средний % скрипта',
                    type: 'line',
                    xAxisIndex: 1,
                    data: data.script_scores,
                    itemStyle: { color: '#ee6666' },
                    lineStyle: { width: 3 },
                    symbolSize: 8,
                    label: {
                        show: true,
                        position: 'right',
                        formatter: '{c}%',
                        fontSize: 10
                    }
                }
            ]
        };

        charts.managers.setOption(option);
    }

    /**
     * Update script quality chart
     */
    function updateScriptQualityChart(data) {
        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                },
                formatter: function(params) {
                    const item = params[0];
                    const scriptItem = data.script_items[item.dataIndex];
                    return `${scriptItem.name}<br/>Выполнено: ${item.value}<br/>Процент: ${scriptItem.percentage}%`;
                }
            },
            xAxis: {
                type: 'category',
                data: data.script_items.map(item => item.name),
                axisLabel: {
                    interval: 0,
                    rotate: 45,
                    fontSize: 11
                }
            },
            yAxis: {
                type: 'value',
                name: 'Количество'
            },
            series: [{
                type: 'bar',
                data: data.script_items.map(item => item.checked),
                itemStyle: {
                    color: '#91cc75'
                },
                label: {
                    show: true,
                    position: 'top',
                    formatter: function(params) {
                        return data.script_items[params.dataIndex].percentage + '%';
                    }
                }
            }]
        };

        charts.scriptQuality.setOption(option);
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
