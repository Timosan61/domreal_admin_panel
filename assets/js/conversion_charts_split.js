/**
 * Conversion Charts - Split View (First vs Repeat)
 * Две отдельные функции для отрисовки графиков конверсии
 */

// Экспортируем функцию в глобальную область
window.drawSingleConversionChart = function(data, type, charts) {
    const isFirst = type === 'first';
    const chart = isFirst ? charts.firstCallConversion : charts.repeatCallConversion;
    const chartId = isFirst ? 'first-call-conversion-chart' : 'repeat-call-conversion-chart';
    const color = isFirst ? '#2196F3' : '#FF9800';
    const colorLight = isFirst ? '#42A5F5' : '#FFB74D';
    const title = isFirst ? 'Первые звонки' : 'Повторные звонки';

    console.log(`[${type}] drawSingleConversionChart`);

    if (!data || !data.managers || data.managers.length === 0) {
        console.warn(`No ${type} call conversion data`);
        chart.setOption({
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

    // Создаем массив объектов для сортировки
    const dataArray = data.managers.map((manager, idx) => ({
        manager: manager,
        department: data.departments[idx],
        firstConversion: data.first_conversion[idx] || 0,
        repeatConversion: data.repeat_conversion[idx] || 0,
        firstTotal: data.first_total[idx] || 0,
        repeatTotal: data.repeat_total[idx] || 0,
        totalCalls: data.total_calls[idx] || 0
    }));

    // Сортируем по соответствующей конверсии (по убыванию)
    dataArray.sort((a, b) => {
        const convA = isFirst ? a.firstConversion : a.repeatConversion;
        const convB = isFirst ? b.firstConversion : b.repeatConversion;
        return convB - convA; // DESC
    });

    // РЕВЕРС массивов - лучшие вверху, худшие внизу (для горизонтального bar chart)
    const reversedManagers = dataArray.map(item => item.manager).reverse();
    const reversedConversion = isFirst ?
        dataArray.map(item => item.firstConversion).reverse() :
        dataArray.map(item => item.repeatConversion).reverse();
    const reversedTotal = isFirst ?
        dataArray.map(item => item.firstTotal).reverse() :
        dataArray.map(item => item.repeatTotal).reverse();

    // Подготовка данных для оси Y
    const managers = reversedManagers.map((manager, idx) => {
        const total = reversedTotal[idx];
        return `${manager} (${total} зв.)`;
    });

    // Средняя конверсия
    const avgConversion = isFirst ?
        (data.summary?.avg_first_conversion || 0) :
        (data.summary?.avg_repeat_conversion || 0);

    // Динамическая высота с ограничением
    const calculatedHeight = managers.length * 40 + 150;
    const chartHeight = Math.min(Math.max(500, calculatedHeight), 700);
    const chartContainer = document.getElementById(chartId);

    console.log(`[${type}] Managers: ${managers.length}, Height: ${chartHeight}px`);

    chartContainer.style.height = chartHeight + 'px';
    chartContainer.style.maxHeight = '700px';
    chart.resize();

    // Конфигурация графика
    const option = {
        title: {
            text: `Средняя конверсия: ${avgConversion}%`,
            left: 'center',
            top: 5,
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
                const conv = reversedConversion[idx];
                const total = reversedTotal[idx];

                return `<b>${managerName}</b><br/>
                        Всего звонков: ${total}<br/>
                        Конверсия: ${conv}%`;
            }
        },
        grid: {
            left: '25%',
            right: calculatedHeight > 700 ? '12%' : '10%',
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
                name: title,
                type: 'bar',
                data: reversedConversion,
                itemStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 1,
                        y2: 0,
                        colorStops: [
                            { offset: 0, color: color },
                            { offset: 1, color: colorLight }
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
                fillerColor: isFirst ? 'rgba(33, 150, 243, 0.15)' : 'rgba(255, 152, 0, 0.15)',
                borderColor: color
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
        console.log(`[${type} DataZoom] Visible: ${visiblePercent.toFixed(1)}%`);
    }

    chart.setOption(option, true);

    // Click handler
    chart.off('click');
    chart.on('click', function(params) {
        if (params.componentType === 'series') {
            const idx = params.dataIndex;
            const managerName = reversedManagers[idx];
            const callType = isFirst ? 'first_call' : 'repeat_call';
            if (window.openCallsWithFilters) {
                window.openCallsWithFilters(null, callType, managerName);
            }
        }
    });
}
