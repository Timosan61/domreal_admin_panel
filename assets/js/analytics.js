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
        firstCallScores: null,
        repeatCallScores: null,
        firstCallResults: null,
        repeatCallResults: null,
        firstCallConversion: null,
        repeatCallConversion: null
    };

    // Current filters
    let currentFilters = {
        date_from: null,
        date_to: null,
        departments: [],
        managers: [],
        crm_stages: []
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

        // CRM Stages multi-select
        const crmDisplay = document.getElementById('crm-stages-display');
        const crmDropdown = document.getElementById('crm-stages-dropdown');

        crmDisplay.addEventListener('click', function() {
            const isOpening = !crmDropdown.classList.contains('active');
            crmDropdown.classList.toggle('active');
            document.getElementById('departments-dropdown').classList.remove('active');
            document.getElementById('managers-dropdown').classList.remove('active');

            if (isOpening) {
                const searchField = document.getElementById('crm-stages-search');
                searchField.value = '';
                filterOptions('crm-stages', '');
            }
        });

        // CRM buttons
        document.getElementById('crm-stages-select-all').addEventListener('click', function(e) {
            e.stopPropagation();
            selectAllOptions('crm-stages');
        });

        document.getElementById('crm-stages-clear').addEventListener('click', function(e) {
            e.stopPropagation();
            clearAllOptions('crm-stages');
        });

        // CRM search field
        document.getElementById('crm-stages-search').addEventListener('input', function(e) {
            e.stopPropagation();
            filterOptions('crm-stages', e.target.value);
        });

        document.getElementById('crm-stages-search').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Close CRM dropdown when clicking outside
        const originalClickHandler = document.addEventListener;
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.multi-select-wrapper')) {
                crmDropdown.classList.remove('active');
            }
        });
    }

    /**
     * Load departments and managers for filters
     */
    async function loadFilterOptions() {
        try {
            // Load departments
            const deptResponse = await fetchWithRetry('/api/filters/departments.php');
            const deptData = await deptResponse.json();
            console.log('Departments filter data:', deptData);

            if (deptData.success) {
                multiSelectData.departments = deptData.data;
                renderMultiSelectOptions('departments', deptData.data);
            }

            // Load managers
            const mgrResponse = await fetchWithRetry('/api/filters/managers.php');
            const mgrData = await mgrResponse.json();
            console.log('Managers filter data:', mgrData);

            if (mgrData.success) {
                multiSelectData.managers = mgrData.data;
                renderMultiSelectOptions('managers', mgrData.data);
            }

            // Load CRM stages
            const crmResponse = await fetchWithRetry('/api/crm_stages.php');
            const crmData = await crmResponse.json();
            console.log('CRM stages filter data:', crmData);

            if (crmData.success) {
                // Format: "Покупатели → Новый лид"
                const crmStages = crmData.data.map(stage =>
                    `${stage.crm_funnel_name}:${stage.crm_step_name}`
                );
                multiSelectData.crm_stages = crmStages;
                renderMultiSelectOptions('crm-stages', crmStages.map(stage => {
                    const [funnel, step] = stage.split(':');
                    return `${funnel} → ${step}`;
                }));
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
        const funnelEl = document.getElementById('funnel-chart');
        const dynamicsEl = document.getElementById('dynamics-chart');
        const firstCallScoresEl = document.getElementById('first-call-scores-chart');
        const repeatCallScoresEl = document.getElementById('repeat-call-scores-chart');
        const firstCallResultsEl = document.getElementById('first-call-results-chart');
        const repeatCallResultsEl = document.getElementById('repeat-call-results-chart');
        const firstCallConversionEl = document.getElementById('first-call-conversion-chart');
        const repeatCallConversionEl = document.getElementById('repeat-call-conversion-chart');

        if (!funnelEl || !dynamicsEl || !firstCallScoresEl || !repeatCallScoresEl || !firstCallResultsEl || !repeatCallResultsEl || !firstCallConversionEl || !repeatCallConversionEl) {
            console.error('One or more chart containers not found:', {
                funnel: !!funnelEl,
                dynamics: !!dynamicsEl,
                firstCallScores: !!firstCallScoresEl,
                repeatCallScores: !!repeatCallScoresEl,
                firstCallResults: !!firstCallResultsEl,
                repeatCallResults: !!repeatCallResultsEl,
                firstCallConversion: !!firstCallConversionEl,
                repeatCallConversion: !!repeatCallConversionEl
            });
            return;
        }

        charts.funnel = echarts.init(funnelEl);
        charts.dynamics = echarts.init(dynamicsEl);
        charts.firstCallScores = echarts.init(firstCallScoresEl);
        charts.repeatCallScores = echarts.init(repeatCallScoresEl);
        charts.firstCallResults = echarts.init(firstCallResultsEl);
        charts.repeatCallResults = echarts.init(repeatCallResultsEl);
        charts.firstCallConversion = echarts.init(firstCallConversionEl);
        charts.repeatCallConversion = echarts.init(repeatCallConversionEl);

        console.log('All charts initialized successfully');

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

        // Resize communication charts
        if (window.communicationCharts && window.communicationCharts.resize) {
            window.communicationCharts.resize();
        }

        // Resize funnel by manager chart
        if (window.funnelByManagerDashboard && window.funnelByManagerDashboard.resize) {
            window.funnelByManagerDashboard.resize();
        }
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
        currentFilters.crm_stages = getMultiSelectValues('crm-stages');

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
        updateMultiSelectDisplay('crm-stages');

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

        if (currentFilters.crm_stages.length > 0) {
            // Convert display format back to "funnel:step" format
            const crmStagesValues = currentFilters.crm_stages.map(stage => {
                // "Покупатели → Новый лид" => "Покупатели:Новый лид"
                return stage.replace(' → ', ':');
            });
            params.append('crm_stages', crmStagesValues.join(','));
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

            const [kpiData, funnelData, dynamicsData,
                   firstCallScoresData, repeatCallScoresData, firstCallResultsData, repeatCallResultsData, firstCallConversionData] = await Promise.all([
                fetchWithRetry(`/api/analytics/kpi.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/funnel.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/dynamics.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/first_call_scores.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/repeat_call_scores.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/first_call_results.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/repeat_call_results.php?${queryString}`).then(r => r.json()),
                fetchWithRetry(`/api/analytics/first_call_conversion.php?${queryString}`).then(r => r.json())
            ]);

            console.log('All API responses:', {
                kpiData,
                funnelData,
                dynamicsData,
                firstCallScoresData,
                repeatCallScoresData
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

            if (firstCallScoresData.success) {
                updateFirstCallScoresChart(firstCallScoresData.data);
            } else {
                console.error('First call scores API error:', firstCallScoresData);
            }

            if (repeatCallScoresData.success) {
                updateRepeatCallScoresChart(repeatCallScoresData.data);
            } else {
                console.error('Repeat call scores API error:', repeatCallScoresData);
            }

            if (firstCallResultsData.success) {
                updateFirstCallResultsChart(firstCallResultsData.data);
            } else {
                console.error('First call results API error:', firstCallResultsData);
            }

            if (repeatCallResultsData.success) {
                updateRepeatCallResultsChart(repeatCallResultsData.data);
            } else {
                console.error('Repeat call results API error:', repeatCallResultsData);
            }

            if (firstCallConversionData.success) {
                updateFirstCallConversionChart(firstCallConversionData.data);
            } else {
                console.error('First call conversion API error:', firstCallConversionData);
            }

            // Load communication metrics charts
            if (window.communicationCharts) {
                console.log('Loading communication metrics charts');
                await Promise.all([
                    window.communicationCharts.loadInterruptions(currentFilters),
                    window.communicationCharts.loadTalkListen(currentFilters)
                ]);
            }

            // Load funnel by manager dashboard
            if (window.funnelByManagerDashboard) {
                console.log('Loading funnel by manager dashboard');
                window.funnelByManagerDashboard.load(currentFilters);
            }

        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            alert('Ошибка загрузки данных аналитики');
        } finally {
            hideLoading();

            // Прокрутка вверх после загрузки всех данных
            setTimeout(() => {
                const analyticsBody = document.querySelector('.analytics-body');
                if (analyticsBody) {
                    analyticsBody.scrollTop = 0;
                }
            }, 200);
        }
    }

    /**
     * Update KPI cards
     */
    function updateKPICards(data) {
        document.getElementById('kpi-total-calls').textContent = data.total_calls.toLocaleString();
        document.getElementById('kpi-first-calls').textContent = data.first_calls.toLocaleString();
        document.getElementById('kpi-repeat-calls').textContent = data.repeat_calls.toLocaleString();
        document.getElementById('kpi-failed-calls').textContent = data.failed_calls.toLocaleString();
        document.getElementById('kpi-successful-calls').textContent = data.showing_scheduled.toLocaleString();
        document.getElementById('kpi-conversion-rate').textContent = data.showing_completed.toLocaleString();
    }

    /**
     * Initialize KPI click handlers for drilldown
     */
    function initKPIClickHandlers() {
        // Всего звонков
        const totalCallsCard = document.getElementById('kpi-total-calls');
        if (totalCallsCard) {
            totalCallsCard.parentElement.style.cursor = 'pointer';
            totalCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters();
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

        // Повторные звонки
        const repeatCallsCard = document.getElementById('kpi-repeat-calls');
        if (repeatCallsCard) {
            repeatCallsCard.parentElement.style.cursor = 'pointer';
            repeatCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters(null, 'repeat_call');
            });
        }

        // Несостоявшиеся звонки
        const failedCallsCard = document.getElementById('kpi-failed-calls');
        if (failedCallsCard) {
            failedCallsCard.parentElement.style.cursor = 'pointer';
            failedCallsCard.parentElement.addEventListener('click', () => {
                openCallsWithFilters(null, 'failed_call');
            });
        }

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
    }

    /**
     * Open calls page with filters from analytics
     */
    function openCallsWithFilters(callResult = null, callType = null, managerName = null) {
        const params = new URLSearchParams();

        // Передаем фильтры из аналитики
        params.set('date_from', currentFilters.date_from);
        params.set('date_to', currentFilters.date_to);

        if (currentFilters.departments.length > 0) {
            params.set('departments', currentFilters.departments.join(','));
        }

        // Если передан конкретный менеджер - используем его, иначе фильтр из панели
        if (managerName) {
            params.set('managers', managerName);
        } else if (currentFilters.managers.length > 0) {
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

    // Экспортируем функцию в глобальную область для использования из других модулей
    window.openCallsWithFilters = openCallsWithFilters;

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

    /**
     * Update first call scores chart
     */
    function updateFirstCallScoresChart(data) {
        console.log('updateFirstCallScoresChart data:', data);

        if (!data || !data.managers || data.managers.length === 0) {
            console.warn('No first call scores data');
            charts.firstCallScores.setOption({
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

        // РЕВЕРС массивов - лучшие вверху, худшие внизу
        const reversedManagers = [...data.managers].reverse();
        const reversedTotalCalls = [...data.total_calls].reverse();
        const reversedAvgScores = [...data.avg_scores].reverse();
        const reversedScore0_25 = [...data.score_distribution.score_0_25].reverse();
        const reversedScore25_50 = [...data.score_distribution.score_25_50].reverse();
        const reversedScore50_75 = [...data.score_distribution.score_50_75].reverse();
        const reversedScore75_100 = [...data.score_distribution.score_75_100].reverse();

        // Подготовка данных для stacked bar
        const managers = reversedManagers.map((manager, idx) => {
            const totalCalls = reversedTotalCalls[idx];
            const avgScore = reversedAvgScores[idx];
            return `${manager} (${totalCalls} зв., ${avgScore}%)`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('first-call-scores-chart');

        console.log(`[First Scores] Managers: ${managers.length}, Calculated: ${calculatedHeight}px, Final: ${chartHeight}px`);

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        charts.firstCallScores.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                },
                formatter: function(params) {
                    const managerName = reversedManagers[params[0].dataIndex];
                    const totalCalls = reversedTotalCalls[params[0].dataIndex];
                    const avgScore = reversedAvgScores[params[0].dataIndex];
                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/>`;
                    tooltip += `Средний балл: ${avgScore}%<br/><br/>`;
                    tooltip += `<b>Распределение оценок:</b><br/>`;
                    params.forEach(item => {
                        tooltip += `${item.marker} ${item.seriesName}: ${item.value}<br/>`;
                    });
                    return tooltip;
                }
            },
            legend: {
                data: ['0-25% (красный)', '25-50% (оранжевый)', '50-75% (желтый)', '75-100% (зеленый)'],
                top: 5,
                left: 'center'
            },
            grid: {
                left: '20%',
                right: '10%',
                bottom: '10%',
                top: '8%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Количество звонков'
            },
            yAxis: {
                type: 'category',
                data: managers,
                axisLabel: {
                    interval: 0,
                    fontSize: 11
                }
            },
            series: [
                {
                    name: '0-25% (красный)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore0_25,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#e53935' },
                                { offset: 1, color: '#ef5350' }
                            ]
                        }
                    }
                },
                {
                    name: '25-50% (оранжевый)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore25_50,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#ff9800' },
                                { offset: 1, color: '#ffa726' }
                            ]
                        }
                    }
                },
                {
                    name: '50-75% (желтый)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore50_75,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#fdd835' },
                                { offset: 1, color: '#ffeb3b' }
                            ]
                        }
                    }
                },
                {
                    name: '75-100% (зеленый)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore75_100,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#66bb6a' },
                                { offset: 1, color: '#81c784' }
                            ]
                        }
                    }
                }
            ]
        };

        // Добавляем DataZoom если данных много
        if (calculatedHeight > 700) {
            const visiblePercent = (700 / calculatedHeight * 100);
            option.dataZoom = [
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10,
                    width: 20,
                    start: 100 - visiblePercent,  // Показываем верх списка (лучшие результаты)
                    end: 100,
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
                    start: 100 - visiblePercent,  // Показываем верх списка
                    end: 100,
                    zoomOnMouseWheel: false,
                    moveOnMouseWheel: true,
                    zoomLock: true
                }
            ];
            console.log(`[First Scores DataZoom] Visible: ${visiblePercent.toFixed(1)}%`);
        }

        charts.firstCallScores.setOption(option);

        // Drilldown при клике
        charts.firstCallScores.off('click');
        charts.firstCallScores.on('click', function(params) {
            if (params.componentType === 'series') {
                const managerName = reversedManagers[params.dataIndex];
                openCallsWithFilters(null, 'first_call', managerName);
            }
        });
    }

    /**
     * Update repeat call scores chart
     */
    function updateRepeatCallScoresChart(data) {
        console.log('updateRepeatCallScoresChart data:', data);

        if (!data || !data.managers || data.managers.length === 0) {
            console.warn('No repeat call scores data');
            charts.repeatCallScores.setOption({
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

        // РЕВЕРС массивов - лучшие вверху, худшие внизу
        const reversedManagers = [...data.managers].reverse();
        const reversedTotalCalls = [...data.total_calls].reverse();
        const reversedAvgScores = [...data.avg_scores].reverse();
        const reversedScore0_25 = [...data.score_distribution.score_0_25].reverse();
        const reversedScore25_50 = [...data.score_distribution.score_25_50].reverse();
        const reversedScore50_75 = [...data.score_distribution.score_50_75].reverse();
        const reversedScore75_100 = [...data.score_distribution.score_75_100].reverse();

        // Подготовка данных для stacked bar
        const managers = reversedManagers.map((manager, idx) => {
            const totalCalls = reversedTotalCalls[idx];
            const avgScore = reversedAvgScores[idx];
            return `${manager} (${totalCalls} зв., ${avgScore}%)`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('repeat-call-scores-chart');

        console.log(`[Repeat Scores] Managers: ${managers.length}, Calculated: ${calculatedHeight}px, Final: ${chartHeight}px`);

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        charts.repeatCallScores.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                },
                formatter: function(params) {
                    const managerName = reversedManagers[params[0].dataIndex];
                    const totalCalls = reversedTotalCalls[params[0].dataIndex];
                    const avgScore = reversedAvgScores[params[0].dataIndex];
                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/>`;
                    tooltip += `Средний балл: ${avgScore}%<br/><br/>`;
                    tooltip += `<b>Распределение оценок:</b><br/>`;
                    params.forEach(item => {
                        tooltip += `${item.marker} ${item.seriesName}: ${item.value}<br/>`;
                    });
                    return tooltip;
                }
            },
            legend: {
                data: ['0-25% (красный)', '25-50% (оранжевый)', '50-75% (желтый)', '75-100% (зеленый)'],
                top: 5,
                left: 'center'
            },
            grid: {
                left: '20%',
                right: '10%',
                bottom: '10%',
                top: '8%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Количество звонков'
            },
            yAxis: {
                type: 'category',
                data: managers,
                axisLabel: {
                    interval: 0,
                    fontSize: 11
                }
            },
            series: [
                {
                    name: '0-25% (красный)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore0_25,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#e53935' },
                                { offset: 1, color: '#ef5350' }
                            ]
                        }
                    }
                },
                {
                    name: '25-50% (оранжевый)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore25_50,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#ff9800' },
                                { offset: 1, color: '#ffa726' }
                            ]
                        }
                    }
                },
                {
                    name: '50-75% (желтый)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore50_75,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#fdd835' },
                                { offset: 1, color: '#ffeb3b' }
                            ]
                        }
                    }
                },
                {
                    name: '75-100% (зеленый)',
                    type: 'bar',
                    stack: 'total',
                    data: reversedScore75_100,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#66bb6a' },
                                { offset: 1, color: '#81c784' }
                            ]
                        }
                    }
                }
            ]
        };

        // Добавляем DataZoom если данных много
        if (calculatedHeight > 700) {
            const visiblePercent = (700 / calculatedHeight * 100);
            option.dataZoom = [
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10,
                    width: 20,
                    start: 100 - visiblePercent,  // Показываем верх списка (лучшие результаты)
                    end: 100,
                    textStyle: {
                        fontSize: 10
                    },
                    handleSize: '80%',
                    showDetail: false,
                    zoomLock: true,
                    fillerColor: 'rgba(255, 152, 0, 0.15)',
                    borderColor: '#FF9800'
                },
                {
                    type: 'inside',
                    yAxisIndex: 0,
                    start: 100 - visiblePercent,  // Показываем верх списка
                    end: 100,
                    zoomOnMouseWheel: false,
                    moveOnMouseWheel: true,
                    zoomLock: true
                }
            ];
            console.log(`[Repeat Scores DataZoom] Visible: ${visiblePercent.toFixed(1)}%`);
        }

        charts.repeatCallScores.setOption(option);

        // Drilldown при клике
        charts.repeatCallScores.off('click');
        charts.repeatCallScores.on('click', function(params) {
            if (params.componentType === 'series') {
                const managerName = reversedManagers[params.dataIndex];
                openCallsWithFilters(null, 'repeat_call', managerName);
            }
        });
    }

    /**
     * Update first call results chart (распределение результатов первого звонка)
     */
    function updateFirstCallResultsChart(data) {
        console.log('updateFirstCallResultsChart data:', data);

        if (!data || !data.managers || data.managers.length === 0) {
            console.warn('No first call results data');
            charts.firstCallResults.setOption({
                title: {
                    text: 'Нет данных для отображения',
                    left: 'center',
                    top: 'middle',
                    textStyle: { color: '#999', fontSize: 14 }
                }
            });
            return;
        }

        // РЕВЕРС - лучшие вверху (по total_calls)
        const indices = data.managers.map((_, idx) => idx);
        indices.reverse();

        const managers = indices.map(idx => {
            const totalCalls = data.total_calls[idx];
            return `${data.managers[idx]} (${totalCalls} зв.)`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('first-call-results-chart');

        console.log(`[First Results] Managers: ${managers.length}, Calculated: ${calculatedHeight}px, Final: ${chartHeight}px`);

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        charts.firstCallResults.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: function(params) {
                    const idx = indices[params[0].dataIndex];
                    const managerName = data.managers[idx];
                    const totalCalls = data.total_calls[idx];
                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/><br/>`;
                    tooltip += `<b>Распределение результатов:</b><br/>`;
                    params.forEach(item => {
                        if (item.value > 0) {
                            tooltip += `${item.marker} ${item.seriesName}: ${item.value}<br/>`;
                        }
                    });
                    return tooltip;
                }
            },
            legend: {
                data: [
                    '📅 Назначен показ', '🏠 Показ проведен', '📤 Отправлены варианты',
                    '⏳ Думает', '⏸️ Ожидается ответ', '📞 Консультация',
                    '📵 Недозвон', '❌ Отказ', '📞 Личный/нерабочий'
                ],
                show: false  // Скрыта как в оценках
            },
            grid: {
                left: '25%',
                right: '10%',
                bottom: '15%',
                top: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Количество звонков'
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
                // ПОЗИТИВНЫЕ (зеленые оттенки)
                {
                    name: '📅 Назначен показ',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.show_scheduled[idx]),
                    itemStyle: { color: '#66bb6a' }
                },
                {
                    name: '🏠 Показ проведен',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.show_done[idx]),
                    itemStyle: { color: '#81c784' }
                },
                {
                    name: '📤 Отправлены варианты',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.materials_sent[idx]),
                    itemStyle: { color: '#aed581' }
                },

                // ОЖИДАНИЕ (желтые оттенки)
                {
                    name: '⏳ Думает',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.thinking[idx]),
                    itemStyle: { color: '#fdd835' }
                },
                {
                    name: '⏸️ Ожидается ответ',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.waiting[idx]),
                    itemStyle: { color: '#ffeb3b' }
                },
                {
                    name: '📞 Консультация',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.consultation[idx]),
                    itemStyle: { color: '#fff59d' }
                },

                // НЕГАТИВНЫЕ (серый + красные оттенки)
                {
                    name: '📵 Недозвон',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.no_answer[idx]),
                    itemStyle: { color: '#9e9e9e' }
                },
                {
                    name: '❌ Отказ',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.rejection[idx]),
                    itemStyle: { color: '#ef5350' }
                },
                {
                    name: '📞 Личный/нерабочий',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.personal[idx]),
                    itemStyle: { color: '#bdbdbd' }
                }
            ]
        };

        // Добавляем DataZoom если данных много
        if (calculatedHeight > 700) {
            const visiblePercent = (700 / calculatedHeight * 100);
            option.dataZoom = [
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10,
                    width: 20,
                    start: 100 - visiblePercent,  // Показываем верх списка (лучшие результаты)
                    end: 100,
                    zoomLock: true,
                    fillerColor: 'rgba(33, 150, 243, 0.15)',
                    borderColor: '#2196F3'
                },
                {
                    type: 'inside',
                    yAxisIndex: 0,
                    start: 100 - visiblePercent,
                    end: 100,
                    zoomOnMouseWheel: false,  // Отключаем зум колесом
                    moveOnMouseWheel: true,   // Включаем скролл колесом
                    zoomLock: true
                }
            ];
        }

        charts.firstCallResults.setOption(option);

        // Drilldown
        charts.firstCallResults.off('click');
        charts.firstCallResults.on('click', function(params) {
            if (params.componentType === 'series') {
                const idx = indices[params.dataIndex];
                const managerName = data.managers[idx];
                openCallsWithFilters(null, 'first_call', managerName);
            }
        });
    }

    /**
     * Update repeat call results chart (распределение результатов повторного звонка)
     */
    function updateRepeatCallResultsChart(data) {
        console.log('updateRepeatCallResultsChart data:', data);

        if (!data || !data.managers || data.managers.length === 0) {
            console.warn('No repeat call results data');
            charts.repeatCallResults.setOption({
                title: {
                    text: 'Нет данных для отображения',
                    left: 'center',
                    top: 'middle',
                    textStyle: { color: '#999', fontSize: 14 }
                }
            });
            return;
        }

        // РЕВЕРС - лучшие вверху (по total_calls)
        const indices = data.managers.map((_, idx) => idx);
        indices.reverse();

        const managers = indices.map(idx => {
            const totalCalls = data.total_calls[idx];
            return `${data.managers[idx]} (${totalCalls} зв.)`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('repeat-call-results-chart');

        console.log(`[Repeat Results] Managers: ${managers.length}, Calculated: ${calculatedHeight}px, Final: ${chartHeight}px`);

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px';
        charts.repeatCallResults.resize();

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: function(params) {
                    const idx = indices[params[0].dataIndex];
                    const managerName = data.managers[idx];
                    const totalCalls = data.total_calls[idx];
                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/><br/>`;
                    tooltip += `<b>Распределение результатов:</b><br/>`;
                    params.forEach(item => {
                        if (item.value > 0) {
                            tooltip += `${item.marker} ${item.seriesName}: ${item.value}<br/>`;
                        }
                    });
                    return tooltip;
                }
            },
            legend: {
                data: [
                    '📅 Назначен показ', '🏠 Показ проведен', '📤 Отправлены варианты',
                    '⏳ Думает', '⏸️ Ожидается ответ', '📞 Консультация',
                    '📵 Недозвон', '❌ Отказ', '📞 Личный/нерабочий'
                ],
                show: false  // Скрыта как в оценках
            },
            grid: {
                left: '25%',
                right: '10%',
                bottom: '15%',
                top: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Количество звонков'
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
                // ПОЗИТИВНЫЕ (зеленые оттенки)
                {
                    name: '📅 Назначен показ',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.show_scheduled[idx]),
                    itemStyle: { color: '#66bb6a' }
                },
                {
                    name: '🏠 Показ проведен',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.show_done[idx]),
                    itemStyle: { color: '#81c784' }
                },
                {
                    name: '📤 Отправлены варианты',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.materials_sent[idx]),
                    itemStyle: { color: '#aed581' }
                },

                // ОЖИДАНИЕ (желтые оттенки)
                {
                    name: '⏳ Думает',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.thinking[idx]),
                    itemStyle: { color: '#fdd835' }
                },
                {
                    name: '⏸️ Ожидается ответ',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.waiting[idx]),
                    itemStyle: { color: '#ffeb3b' }
                },
                {
                    name: '📞 Консультация',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.consultation[idx]),
                    itemStyle: { color: '#fff59d' }
                },

                // НЕГАТИВНЫЕ (серый + красные оттенки)
                {
                    name: '📵 Недозвон',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.no_answer[idx]),
                    itemStyle: { color: '#9e9e9e' }
                },
                {
                    name: '❌ Отказ',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.rejection[idx]),
                    itemStyle: { color: '#ef5350' }
                },
                {
                    name: '📞 Личный/нерабочий',
                    type: 'bar',
                    stack: 'total',
                    data: indices.map(idx => data.distribution.personal[idx]),
                    itemStyle: { color: '#bdbdbd' }
                }
            ]
        };

        // Добавляем DataZoom если данных много
        if (calculatedHeight > 700) {
            const visiblePercent = (700 / calculatedHeight * 100);
            option.dataZoom = [
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10,
                    width: 20,
                    start: 100 - visiblePercent,  // Показываем верх списка (лучшие результаты)
                    end: 100,
                    zoomLock: true,
                    fillerColor: 'rgba(255, 152, 0, 0.15)',  // Оранжевый для повторных
                    borderColor: '#FF9800'
                },
                {
                    type: 'inside',
                    yAxisIndex: 0,
                    start: 100 - visiblePercent,
                    end: 100,
                    zoomOnMouseWheel: false,  // Отключаем зум колесом
                    moveOnMouseWheel: true,   // Включаем скролл колесом
                    zoomLock: true
                }
            ];
        }

        charts.repeatCallResults.setOption(option);

        // Drilldown
        charts.repeatCallResults.off('click');
        charts.repeatCallResults.on('click', function(params) {
            if (params.componentType === 'series') {
                const idx = indices[params.dataIndex];
                const managerName = data.managers[idx];
                openCallsWithFilters(null, 'repeat_call', managerName);
            }
        });
    }

    /**
     * Update Conversion Charts (First and Repeat separately)
     */
    function updateFirstCallConversionChart(data) {
        console.log('updateFirstCallConversionChart data:', data);

        // Вызываем отрисовку двух отдельных графиков
        if (typeof window.drawSingleConversionChart === 'function') {
            // Используем новую функцию из conversion_charts_split.js
            console.log('[Split Charts] Using external drawSingleConversionChart');
            window.drawSingleConversionChart(data, 'first', charts);
            window.drawSingleConversionChart(data, 'repeat', charts);
        } else {
            // Fallback: старая реализация
            console.warn('[Fallback] drawSingleConversionChart not found, using legacy');
            drawConversionChartLegacy(data);
        }
    }

    /**
     * Legacy Draw Conversion Chart
     */
    function drawConversionChartLegacy(data) {
        console.log('[Legacy] drawConversionChartLegacy');

        if (!data || !data.managers || data.managers.length === 0) {
            console.warn('No first call conversion data');
            charts.firstCallConversion.setOption({
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

        // РЕВЕРС массивов - лучшие вверху, худшие внизу
        const reversedManagers = [...data.managers].reverse();
        const reversedFirstConversion = [...data.first_conversion].reverse();
        const reversedRepeatConversion = [...data.repeat_conversion].reverse();
        const reversedOverallConversion = [...data.overall_conversion].reverse();
        const reversedFirstTotal = [...data.first_total].reverse();
        const reversedRepeatTotal = [...data.repeat_total].reverse();
        const reversedTotalCalls = [...data.total_calls].reverse();

        // Подготовка данных для оси Y
        const managers = reversedManagers.map((manager, idx) => {
            const totalCalls = reversedTotalCalls[idx];
            const firstTotal = reversedFirstTotal[idx];
            const repeatTotal = reversedRepeatTotal[idx];
            return `${manager} (1й: ${firstTotal}, пов: ${repeatTotal})`;
        });

        // Динамическая высота с ограничением: минимум 500px, максимум 700px
        const calculatedHeight = managers.length * 40 + 150;
        const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
        const chartContainer = document.getElementById('first-call-conversion-chart');

        console.log(`[Conversion Chart] Managers: ${managers.length}, Calculated: ${calculatedHeight}px, Final: ${chartHeight}px`);

        chartContainer.style.height = chartHeight + 'px';
        chartContainer.style.maxHeight = '700px'; // Дополнительное ограничение
        charts.firstCallConversion.resize();

        // Summary statistics
        const summary = data.summary || {};
        const avgFirstConv = summary.avg_first_conversion || 0;
        const avgRepeatConv = summary.avg_repeat_conversion || 0;
        const difference = summary.difference || 0;

        // Создаем базовую конфигурацию графика
        const option = {
            title: {
                text: `Среднее: 1-й зв. ${avgFirstConv}% | Повтор. ${avgRepeatConv}% | Разница ${difference > 0 ? '+' : ''}${difference}%`,
                left: 'center',
                top: 10,
                textStyle: {
                    fontSize: 13,
                    fontWeight: 'normal',
                    color: '#666'
                }
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                },
                formatter: function(params) {
                    const idx = params[0].dataIndex;
                    const managerName = reversedManagers[idx];
                    const firstConv = reversedFirstConversion[idx];
                    const repeatConv = reversedRepeatConversion[idx];
                    const overallConv = reversedOverallConversion[idx];
                    const firstTotal = reversedFirstTotal[idx];
                    const repeatTotal = reversedRepeatTotal[idx];
                    const totalCalls = reversedTotalCalls[idx];

                    let tooltip = `<b>${managerName}</b><br/>`;
                    tooltip += `Всего звонков: ${totalCalls}<br/><br/>`;
                    tooltip += `<b>Первые звонки:</b> ${firstTotal} зв. → ${firstConv}%<br/>`;
                    tooltip += `<b>Повторные звонки:</b> ${repeatTotal} зв. → ${repeatConv}%<br/>`;
                    tooltip += `<b>Общая конверсия:</b> ${overallConv}%<br/>`;

                    const diff = firstConv - repeatConv;
                    if (diff > 0) {
                        tooltip += `<br/><span style="color: #4caf50;">▲ Первые лучше на ${diff.toFixed(1)}%</span>`;
                    } else if (diff < 0) {
                        tooltip += `<br/><span style="color: #f44336;">▼ Повторные лучше на ${Math.abs(diff).toFixed(1)}%</span>`;
                    } else {
                        tooltip += `<br/><span style="color: #999;">= Одинаковая конверсия</span>`;
                    }

                    return tooltip;
                }
            },
            legend: {
                data: ['Первый звонок', 'Повторный звонок', 'Общая конверсия'],
                top: 10,
                left: 'center'
            },
            grid: {
                left: '25%',
                right: calculatedHeight > 700 ? '12%' : '10%',  // Больше места для ползунка
                bottom: '5%',
                top: '10%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Конверсия (%)',
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
                    fontSize: 11
                }
            },
            series: [
                {
                    name: 'Первый звонок',
                    type: 'bar',
                    data: reversedFirstConversion,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#2196F3' },
                                { offset: 1, color: '#42A5F5' }
                            ]
                        }
                    },
                    label: {
                        show: true,
                        position: 'right',
                        formatter: '{c}%',
                        fontSize: 10
                    },
                    barGap: '10%'
                },
                {
                    name: 'Повторный звонок',
                    type: 'bar',
                    data: reversedRepeatConversion,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#FF9800' },
                                { offset: 1, color: '#FFB74D' }
                            ]
                        }
                    },
                    label: {
                        show: true,
                        position: 'right',
                        formatter: '{c}%',
                        fontSize: 10
                    }
                },
                {
                    name: 'Общая конверсия',
                    type: 'bar',
                    data: reversedOverallConversion,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 0,
                            colorStops: [
                                { offset: 0, color: '#9E9E9E' },
                                { offset: 1, color: '#BDBDBD' }
                            ]
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

        // Добавляем DataZoom только если данных больше, чем помещается на экране
        if (calculatedHeight > 700) {
            const visiblePercent = (700 / calculatedHeight * 100);
            option.dataZoom = [
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10,
                    width: 20,
                    start: 100 - visiblePercent,  // Показываем верх списка (лучшие результаты)
                    end: 100,
                    textStyle: {
                        fontSize: 10
                    },
                    handleSize: '80%',
                    showDetail: false,
                    zoomLock: true,  // Блокируем zoom, только прокрутка
                    fillerColor: 'rgba(33, 150, 243, 0.15)',
                    borderColor: '#2196F3'
                },
                {
                    type: 'inside',
                    yAxisIndex: 0,
                    start: 100 - visiblePercent,  // Показываем верх списка
                    end: 100,
                    zoomOnMouseWheel: false,  // Отключаем zoom колесиком
                    moveOnMouseWheel: true,   // Включаем прокрутку колесиком
                    zoomLock: true            // Полная блокировка zoom
                }
            ];
            console.log(`[DataZoom] Enabled. Visible: ${visiblePercent.toFixed(1)}% of ${managers.length} managers`);
        }

        charts.firstCallConversion.setOption(option, true);

        // Add click handler for drill-down
        charts.firstCallConversion.off('click');
        charts.firstCallConversion.on('click', function(params) {
            if (params.componentType === 'series') {
                const idx = params.dataIndex;
                const managerName = reversedManagers[idx];
                const isFirstCall = params.seriesName === 'Первый звонок' ? 'first_call' :
                                   params.seriesName === 'Повторный звонок' ? 'repeat_call' : null;
                if (isFirstCall) {
                    openCallsWithFilters(null, isFirstCall, managerName);
                }
            }
        });
    }

    // Export currentFilters to global scope for communication_charts.js
    window.currentFilters = currentFilters;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
