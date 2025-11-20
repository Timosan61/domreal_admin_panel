/**
 * Analytics Theme Handler
 * Автоматически обновляет графики ECharts при смене темы
 */

(function() {
    'use strict';

    // Слушаем событие смены темы
    window.addEventListener('themeChanged', function(e) {
        const isDark = e.detail.theme === 'dark';

        // Даем небольшую задержку для применения CSS
        setTimeout(() => {
            updateChartsTheme(isDark);
        }, 100);
    });

    // Применяем тему при загрузке страницы
    window.addEventListener('load', function() {
        const isDark = window.themeSwitcher && window.themeSwitcher.isDarkTheme();
        if (isDark) {
            setTimeout(() => {
                updateChartsTheme(true);
            }, 500);
        }
    });

    /**
     * Обновляет тему для всех графиков ECharts
     */
    function updateChartsTheme(isDark) {
        // Если charts объект недоступен, просто перезагружаем страницу
        if (typeof window.charts === 'undefined' || !window.charts) {
            console.log('Charts not loaded yet, will apply theme on next render');
            return;
        }

        // Получаем цвета для темы
        const textColor = isDark ? '#ffffff' : '#000000';
        const mutedColor = isDark ? '#98989D' : '#666666';
        const backgroundColor = isDark ? '#1c1c1e' : '#ffffff';
        const axisLineColor = isDark ? '#38383a' : '#E5E5EA';
        const splitLineColor = isDark ? '#38383a' : '#E5E5EA';

        // Общие настройки для всех графиков
        const commonOptions = {
            textStyle: {
                color: textColor
            },
            legend: {
                textStyle: {
                    color: textColor
                }
            },
            xAxis: {
                axisLine: {
                    lineStyle: {
                        color: axisLineColor
                    }
                },
                axisLabel: {
                    color: textColor
                },
                splitLine: {
                    lineStyle: {
                        color: splitLineColor
                    }
                }
            },
            yAxis: {
                axisLine: {
                    lineStyle: {
                        color: axisLineColor
                    }
                },
                axisLabel: {
                    color: textColor
                },
                splitLine: {
                    lineStyle: {
                        color: splitLineColor
                    }
                }
            },
            tooltip: {
                backgroundColor: isDark ? 'rgba(0, 0, 0, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                borderColor: axisLineColor,
                textStyle: {
                    color: textColor
                }
            }
        };

        // Обновляем каждый график
        Object.keys(window.charts).forEach(chartKey => {
            const chart = window.charts[chartKey];
            if (chart && typeof chart.setOption === 'function') {
                try {
                    const currentOption = chart.getOption();

                    // Применяем общие настройки темы
                    chart.setOption(commonOptions, { notMerge: false });

                    console.log(`Updated theme for chart: ${chartKey}`);
                } catch (e) {
                    console.error(`Error updating chart ${chartKey}:`, e);
                }
            }
        });

        console.log(`Theme updated for ${Object.keys(window.charts).length} charts`);
    }
})();
