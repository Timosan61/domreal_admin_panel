/**
 * JavaScript для страницы списка звонков
 */

// Глобальные переменные
let currentPage = 1;
let currentFilters = {};
let currentSort = { by: 'started_at_utc', order: 'DESC' };
let multiselectInstances = null; // Хранилище для multiselect инстансов

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

/**
 * Сохранение состояния фильтров и сортировки в URL
 */
function saveStateToURL() {
    const params = new URLSearchParams();

    // Сохраняем фильтры
    for (let [key, value] of Object.entries(currentFilters)) {
        if (value) {
            params.set(key, value);
        }
    }

    // Сохраняем сортировку
    if (currentSort.by !== 'started_at_utc' || currentSort.order !== 'DESC') {
        params.set('sort_by', currentSort.by);
        params.set('sort_order', currentSort.order);
    }

    // Сохраняем страницу
    if (currentPage !== 1) {
        params.set('page', currentPage);
    }

    // Обновляем URL без перезагрузки страницы
    const newURL = params.toString() ? `?${params.toString()}` : window.location.pathname;
    window.history.replaceState({}, '', newURL);
}

/**
 * Восстановление состояния из URL
 */
async function loadStateFromURL() {
    const params = new URLSearchParams(window.location.search);

    // Восстанавливаем фильтры
    currentFilters = {};
    const filterKeys = ['call_type', 'date_from', 'date_to', 'search', 'client_phone'];

    // Восстанавливаем обычные фильтры (текстовые поля, обычные селекты)
    filterKeys.forEach(key => {
        const value = params.get(key);
        if (value) {
            currentFilters[key] = value;
            // Обновляем значения в форме
            const element = document.getElementById(key);
            if (element) {
                element.value = value;
            }
        }
    });

    // Восстанавливаем multiselect компоненты
    if (multiselectInstances) {
        // Отделы
        const departments = params.get('departments');
        if (departments) {
            currentFilters['departments'] = departments;
            const departmentMS = multiselectInstances.get('department-multiselect');
            if (departmentMS) {
                const departmentsArray = departments.split(',');
                departmentMS.setValues(departmentsArray);

                // Загружаем менеджеров для выбранных отделов
                await loadManagersByDepartments(departmentsArray);
            }
        }

        // Менеджеры
        const managers = params.get('managers');
        if (managers) {
            currentFilters['managers'] = managers;
            const managerMS = multiselectInstances.get('manager-multiselect');
            if (managerMS) {
                const managersArray = managers.split(',');
                managerMS.setValues(managersArray);
            }
        }

        // Направления звонка
        const directions = params.get('directions');
        if (directions) {
            currentFilters['directions'] = directions;
            const directionMS = multiselectInstances.get('direction-multiselect');
            if (directionMS) {
                const directionsArray = directions.split(',');
                directionMS.setValues(directionsArray);
            }
        }

        // Оценка (ratings)
        const ratings = params.get('ratings');
        if (ratings) {
            currentFilters['ratings'] = ratings;
            const ratingMS = multiselectInstances.get('rating-multiselect');
            if (ratingMS) {
                const ratingsArray = ratings.split(',');
                ratingMS.setValues(ratingsArray);
            }
        }

        // Теги
        const tags = params.get('tags');
        if (tags) {
            currentFilters['tags'] = tags;
            const tagsMS = multiselectInstances.get('tags-multiselect');
            if (tagsMS) {
                const tagsArray = tags.split(',');
                tagsMS.setValues(tagsArray);
            }
        }

        // Результаты звонка
        const callResults = params.get('call_results');
        if (callResults) {
            currentFilters['call_results'] = callResults;
            const resultMS = multiselectInstances.get('result-multiselect');
            if (resultMS) {
                const resultsArray = callResults.split(',');
                resultMS.setValues(resultsArray);
            }
        }
    }

    // Восстанавливаем сортировку
    const sortBy = params.get('sort_by');
    const sortOrder = params.get('sort_order');
    if (sortBy) {
        currentSort.by = sortBy;
        currentSort.order = sortOrder || 'DESC';

        // Обновляем стрелки сортировки в таблице
        document.querySelectorAll('th[data-sort]').forEach(th => {
            const sortField = th.getAttribute('data-sort');
            if (sortField === sortBy) {
                th.textContent = th.textContent.replace(/ [↑↓]/g, '');
                th.textContent += sortOrder === 'DESC' ? ' ↓' : ' ↑';
            }
        });
    }

    // Восстанавливаем страницу
    const page = params.get('page');
    if (page) {
        currentPage = parseInt(page);
    }
}

/**
 * Инициализация страницы
 */
async function initializePage() {
    // Инициализируем multiselect компоненты
    multiselectInstances = initMultiselects();

    await loadFilterOptions();
    await loadStateFromURL(); // Восстанавливаем состояние из URL (теперь async)
    setupEventListeners();
    await loadCalls();
}

/**
 * Загрузка доступных значений для фильтров
 */
async function loadFilterOptions() {
    try {
        const response = await fetch('api/filters.php');
        const result = await response.json();

        if (result.success) {
            const { departments, managers, call_types } = result.data;

            // Заполняем multiselect отделов
            const departmentMS = multiselectInstances.get('department-multiselect');
            if (departmentMS) {
                const options = departments.map(dept => ({
                    name: 'departments[]',
                    value: dept,
                    label: dept
                }));
                departmentMS.setOptions(options);
            }

            // Заполняем multiselect менеджеров (начальная загрузка - все менеджеры)
            const managerMS = multiselectInstances.get('manager-multiselect');
            if (managerMS) {
                const options = managers.map(manager => ({
                    name: 'managers[]',
                    value: manager,
                    label: manager
                }));
                managerMS.setOptions(options);
            }

            // Типы звонков уже заданы в HTML
            const callTypeSelect = document.getElementById('call_type');
            call_types.forEach(type => {
                // Проверяем, нет ли уже такой опции
                if (!Array.from(callTypeSelect.options).some(opt => opt.value === type)) {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    callTypeSelect.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Ошибка загрузки фильтров:', error);
    }
}

/**
 * Загрузка менеджеров по выбранным отделам
 */
async function loadManagersByDepartments(departments) {
    try {
        // Если выбрано несколько отделов, загружаем менеджеров для каждого
        let url = 'api/filters.php';
        if (departments && departments.length > 0) {
            // Для простоты пока загружаем менеджеров для первого отдела
            // TODO: можно улучшить, загружая для всех отделов
            url = `api/filters.php?department=${encodeURIComponent(departments[0])}`;
        }

        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            const { managers } = result.data;

            const managerMS = multiselectInstances.get('manager-multiselect');
            if (managerMS) {
                const currentValues = managerMS.getValues();

                const options = managers.map(manager => ({
                    name: 'managers[]',
                    value: manager,
                    label: manager
                }));

                managerMS.setOptions(options);

                // Восстанавливаем выбранные значения, если они есть в новом списке
                const validValues = currentValues.filter(v => managers.includes(v));
                if (validValues.length > 0) {
                    managerMS.setValues(validValues);
                }
            }
        }
    } catch (error) {
        console.error('Ошибка загрузки менеджеров:', error);
    }
}

/**
 * Настройка обработчиков событий
 */
function setupEventListeners() {
    // Фильтры
    const filtersForm = document.getElementById('filters-form');
    filtersForm.addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        currentFilters = getFiltersFromForm();
        saveStateToURL(); // Сохраняем состояние в URL
        loadCalls();
    });

    // Сброс фильтров
    document.getElementById('reset-filters').addEventListener('click', function() {
        filtersForm.reset();

        // Сбрасываем все multiselect
        if (multiselectInstances) {
            multiselectInstances.forEach(instance => {
                instance.clear();
            });
        }

        currentPage = 1;
        currentFilters = {};
        saveStateToURL(); // Сохраняем состояние в URL
        loadManagersByDepartments([]); // Загружаем всех менеджеров
        loadCalls();
    });

    // Зависимый фильтр: при изменении отделов обновляем список менеджеров
    const departmentMS = multiselectInstances.get('department-multiselect');
    if (departmentMS) {
        const departmentCheckboxes = departmentMS.optionsContainer.querySelectorAll('input[type="checkbox"]');
        departmentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const selectedDepartments = departmentMS.getValues();

                // Загружаем менеджеров для выбранных отделов
                loadManagersByDepartments(selectedDepartments);
            });
        });
    }

    // Сортировка по колонкам
    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const sortBy = this.getAttribute('data-sort');

            // Переключаем направление сортировки
            if (currentSort.by === sortBy) {
                currentSort.order = currentSort.order === 'DESC' ? 'ASC' : 'DESC';
            } else {
                currentSort.by = sortBy;
                currentSort.order = 'DESC';
            }

            // Обновляем стрелки сортировки
            document.querySelectorAll('th[data-sort]').forEach(header => {
                header.textContent = header.textContent.replace(/ [↑↓]/g, '');
            });
            this.textContent += currentSort.order === 'DESC' ? ' ↓' : ' ↑';

            saveStateToURL(); // Сохраняем состояние в URL
            loadCalls();
        });
    });
}

/**
 * Получение фильтров из формы
 */
function getFiltersFromForm() {
    const filters = {};
    const form = document.getElementById('filters-form');
    const formData = new FormData(form);

    // Обработка обычных полей (текстовые поля, обычные селекты)
    for (let [key, value] of formData.entries()) {
        // Пропускаем массивы чекбоксов (они обработаются ниже)
        if (key.endsWith('[]')) {
            continue;
        }

        if (value) {
            filters[key] = value;
        }
    }

    // Обработка multiselect компонентов
    if (multiselectInstances) {
        // Отделы
        const departmentMS = multiselectInstances.get('department-multiselect');
        if (departmentMS) {
            const departments = departmentMS.getValues();
            if (departments.length > 0) {
                filters['departments'] = departments.join(',');
            }
        }

        // Менеджеры
        const managerMS = multiselectInstances.get('manager-multiselect');
        if (managerMS) {
            const managers = managerMS.getValues();
            if (managers.length > 0) {
                filters['managers'] = managers.join(',');
            }
        }

        // Направления звонка
        const directionMS = multiselectInstances.get('direction-multiselect');
        if (directionMS) {
            const directions = directionMS.getValues();
            if (directions.length > 0) {
                filters['directions'] = directions.join(',');
            }
        }

        // Оценка (ratings)
        const ratingMS = multiselectInstances.get('rating-multiselect');
        if (ratingMS) {
            const ratings = ratingMS.getValues();
            if (ratings.length > 0) {
                filters['ratings'] = ratings.join(',');
            }
        }

        // Теги
        const tagsMS = multiselectInstances.get('tags-multiselect');
        if (tagsMS) {
            const tags = tagsMS.getValues();
            if (tags.length > 0) {
                filters['tags'] = tags.join(',');
            }
        }

        // Результаты звонка
        const resultMS = multiselectInstances.get('result-multiselect');
        if (resultMS) {
            const results = resultMS.getValues();
            if (results.length > 0) {
                filters['call_results'] = results.join(',');
            }
        }
    }

    return filters;
}

/**
 * Загрузка списка звонков
 */
async function loadCalls() {
    const tbody = document.getElementById('calls-tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="loading">Загрузка данных...</td></tr>';

    try {
        // Формируем URL с параметрами
        const params = new URLSearchParams({
            ...currentFilters,
            page: currentPage,
            per_page: 20,
            sort_by: currentSort.by,
            sort_order: currentSort.order
        });

        console.log('🔍 Отправка фильтров:', currentFilters);
        console.log('📡 API URL:', `api/calls.php?${params}`);

        const response = await fetch(`api/calls.php?${params}`);
        const result = await response.json();

        if (result.success) {
            renderCalls(result.data);
            renderPagination(result.pagination);
            updateStats(result.pagination);
        } else {
            tbody.innerHTML = '<tr><td colspan="11" class="error">Ошибка загрузки данных</td></tr>';
        }
    } catch (error) {
        console.error('Ошибка загрузки звонков:', error);
        tbody.innerHTML = '<tr><td colspan="11" class="error">Ошибка подключения к серверу</td></tr>';
    }
}

/**
 * Отрисовка списка звонков
 */
function renderCalls(calls) {
    const tbody = document.getElementById('calls-tbody');

    if (calls.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">Звонки не найдены</td></tr>';
        return;
    }

    // Получаем текущий URL со state для передачи в страницу деталей
    const currentStateURL = window.location.search;

    tbody.innerHTML = calls.map(call => `
        <tr>
            <td>${escapeHtml(call.employee_name || '-')}</td>
            <td>${formatCallResult(call.call_result, call.is_successful, call.call_type)}</td>
            <td>${formatScriptCompliance(call.script_compliance_score, call.call_type)}</td>
            <td class="summary-cell" data-full-text="${escapeHtml(call.summary_text || '')}">${formatSummary(call.summary_text)}</td>
            <td>${formatDateTime(call.started_at_utc)}</td>
            <td>${formatDirection(call.direction)}</td>
            <td>${formatDuration(call.duration_sec)}</td>
            <td>${escapeHtml(call.client_phone || '-')}</td>
            <td>
                <a href="call_evaluation.php?callid=${encodeURIComponent(call.callid)}&returnState=${encodeURIComponent(currentStateURL)}"
                   class="btn btn-primary btn-sm">
                    Открыть
                </a>
            </td>
            <td>${formatCallType(call.call_type)}</td>
            <td>${escapeHtml(call.department || '-')}</td>
        </tr>
    `).join('');

    // Инициализация tooltip для ячеек резюме
    initSummaryTooltips();
}

/**
 * Отрисовка пагинации
 */
function renderPagination(pagination) {
    const container = document.getElementById('pagination');
    const { page, total_pages } = pagination;

    if (total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = `
        <button ${page === 1 ? 'disabled' : ''} onclick="goToPage(1)">Первая</button>
        <button ${page === 1 ? 'disabled' : ''} onclick="goToPage(${page - 1})">← Назад</button>
        <span class="page-info">Страница ${page} из ${total_pages}</span>
        <button ${page === total_pages ? 'disabled' : ''} onclick="goToPage(${page + 1})">Вперёд →</button>
        <button ${page === total_pages ? 'disabled' : ''} onclick="goToPage(${total_pages})">Последняя</button>
    `;

    container.innerHTML = html;
}

/**
 * Обновление статистики
 */
function updateStats(pagination) {
    document.getElementById('stat-total').textContent = pagination.total;
    document.getElementById('stat-page').textContent = Math.min(pagination.per_page, pagination.total - (pagination.page - 1) * pagination.per_page);
}

/**
 * Переход на страницу
 */
function goToPage(page) {
    currentPage = page;
    saveStateToURL(); // Сохраняем состояние в URL
    loadCalls();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Форматирование даты и времени
 */
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
}

/**
 * Форматирование длительности
 */
function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) return '-';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}м ${secs}с`;
}

/**
 * Форматирование направления звонка
 */
function formatDirection(direction) {
    if (!direction) return '-';
    const directions = {
        'INBOUND': '<span class="badge badge-info">Входящий</span>',
        'OUTBOUND': '<span class="badge badge-success">Исходящий</span>',
        'MISSED': '<span class="badge badge-danger">Пропущенный</span>'
    };
    return directions[direction] || `<span class="badge">${escapeHtml(direction)}</span>`;
}

/**
 * Форматирование типа звонка
 */
function formatCallType(type) {
    if (!type) return '-';
    const types = {
        'first_call': '<span class="badge badge-info">Первый звонок</span>',
        'other': '<span class="badge">Другое</span>'
    };
    return types[type] || `<span class="badge">${escapeHtml(type)}</span>`;
}

/**
 * Форматирование оценки выполнения скрипта (script_compliance_score от 0.00 до 1.00)
 */
function formatScriptCompliance(score, callType) {
    // Оценка скрипта только для первого звонка
    if (callType !== 'first_call') {
        return '<span class="text-muted">н/д</span>';
    }

    if (score === null || score === undefined) return '-';

    const scoreNum = parseFloat(score);
    const percentage = Math.round(scoreNum * 100);
    let className = 'rating-low';

    if (scoreNum >= 0.8) {
        className = 'rating-high';
    } else if (scoreNum >= 0.6) {
        className = 'rating-medium';
    }

    return `<span class="rating-badge ${className}">${percentage}%</span>`;
}

/**
 * Форматирование эмоционального тона и вероятности конверсии
 */
function formatEmotionTone(emotion, conversionProb) {
    if (!emotion && !conversionProb) return '-';

    const prob = parseFloat(conversionProb);
    let badgeClass = 'badge-danger';
    let text = emotion || 'unknown';

    if (prob >= 0.7) {
        badgeClass = 'badge-success';
        text = '🟢 ' + (emotion || 'позитивный');
    } else if (prob >= 0.4) {
        badgeClass = 'badge-warning';
        text = '🟡 ' + (emotion || 'нейтральный');
    } else {
        badgeClass = 'badge-danger';
        text = '🔴 ' + (emotion || 'негативный');
    }

    return `<span class="badge ${badgeClass}">${escapeHtml(text)}</span>`;
}

/**
 * Форматирование результата звонка с учетом call_type
 * Логика совпадает с детальной страницей (call_evaluation.js)
 */
function formatCallResult(result, isSuccessful, callType) {
    // Пытаемся использовать имеющиеся данные
    if (!result && isSuccessful === null) return '-';

    // Если есть результат, отображаем его
    if (result) {
        // Очищаем префикс "Результат:" если есть
        let cleanResult = result.replace(/^Результат:\s*/i, '').trim();

        let badgeClass = 'badge-info'; // По умолчанию синий
        let icon = '';
        const resultLower = cleanResult.toLowerCase();

        // Для первого звонка - специфичные категории
        if (callType === 'first_call') {
            if (resultLower.includes('квалифик')) {
                badgeClass = 'badge-success';
                icon = '📋 ';
            } else if (resultLower.includes('материал') || resultLower.includes('отправ')) {
                badgeClass = 'badge-success';
                icon = '📤 ';
            } else if (resultLower.includes('показ')) {
                badgeClass = 'badge-success';
                icon = '🏠 ';
            } else if (resultLower.includes('назначен перезвон')) {
                badgeClass = 'badge-info';
                icon = '📞 ';
            } else if (resultLower.includes('не целевой') || resultLower.includes('нецелевой')) {
                badgeClass = 'badge-warning';
                icon = '⛔ ';
            } else if (resultLower.includes('отказ')) {
                badgeClass = 'badge-danger';
                icon = '❌ ';
            } else if (resultLower.includes('не дозвон')) {
                badgeClass = 'badge-secondary';
                icon = '📵 ';
            }
        }
        // Для других звонков - стандартные категории
        else {
            if (resultLower.includes('показ')) {
                badgeClass = 'badge-success';
                icon = '🏠 ';
            } else if (resultLower.includes('перезвон')) {
                badgeClass = 'badge-warning';
                icon = '⏰ ';
            } else if (resultLower.includes('думает')) {
                badgeClass = 'badge-info';
                icon = '💭 ';
            } else if (resultLower.includes('отказ')) {
                badgeClass = 'badge-danger';
                icon = '❌ ';
            } else if (resultLower.includes('не дозвон')) {
                badgeClass = 'badge-secondary';
                icon = '📵 ';
            }
        }

        // Общие категории (для любого типа звонка)
        if (resultLower.includes('личн') || resultLower.includes('нерабоч')) {
            badgeClass = 'badge-secondary';
            icon = '👤 ';
        }

        // Если нет спецкатегорий, используем флаг успешности как fallback
        if (!icon && (isSuccessful !== null && isSuccessful !== undefined)) {
            badgeClass = isSuccessful ? 'badge-success' : 'badge-danger';
        }

        return `<span class="badge ${badgeClass}">${icon}${escapeHtml(cleanResult)}</span>`;
    }

    // Иначе используем isSuccessful
    const badgeClass = isSuccessful ? 'badge-success' : 'badge-danger';
    const text = isSuccessful ? 'Успешный' : 'Неуспешный';
    return `<span class="badge ${badgeClass}">${text}</span>`;
}

/**
 * Форматирование резюме звонка с обрезкой текста
 */
function formatSummary(summaryText) {
    if (!summaryText || summaryText.trim() === '') return '-';

    const maxLength = 40;
    const text = summaryText.trim();

    if (text.length > maxLength) {
        return escapeHtml(text.substring(0, maxLength)) + '...';
    }

    return escapeHtml(text);
}

/**
 * Инициализация tooltip для ячеек с резюме
 */
function initSummaryTooltips() {
    const cells = document.querySelectorAll('.summary-cell');

    cells.forEach(cell => {
        const fullText = cell.getAttribute('data-full-text');

        // Показываем tooltip если текст обрезан CSS (scrollWidth) или программно (length > 40)
        if (fullText && fullText.trim() !== '') {
            const isTruncatedByCSS = cell.scrollWidth > cell.clientWidth;
            const isTruncatedByJS = fullText.length > 40;

            if (isTruncatedByCSS || isTruncatedByJS) {
                cell.addEventListener('mouseenter', function(e) {
                    showSummaryTooltip(e, fullText);
                });

                cell.addEventListener('mouseleave', function() {
                    hideSummaryTooltip();
                });

                // Добавляем курсор pointer для индикации интерактивности
                cell.style.cursor = 'pointer';
            }
        }
    });
}

/**
 * Показать tooltip с полным текстом резюме
 */
function showSummaryTooltip(event, text) {
    // Удаляем существующий tooltip если есть
    hideSummaryTooltip();

    const tooltip = document.createElement('div');
    tooltip.className = 'summary-tooltip';
    tooltip.textContent = text;
    tooltip.id = 'summary-tooltip';

    document.body.appendChild(tooltip);

    // Позиционируем tooltip
    const rect = event.target.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    // Позиция: под ячейкой, выровнено по левому краю
    let left = rect.left;
    let top = rect.bottom + 5;

    // Проверяем, не выходит ли tooltip за правую границу экрана
    if (left + tooltipRect.width > window.innerWidth) {
        left = window.innerWidth - tooltipRect.width - 10;
    }

    // Проверяем, не выходит ли tooltip за нижнюю границу экрана
    if (top + tooltipRect.height > window.innerHeight) {
        top = rect.top - tooltipRect.height - 5;
    }

    // Проверяем минимальные отступы от краев экрана
    if (left < 5) left = 5;
    if (top < 5) top = 5;

    tooltip.style.left = left + 'px';
    tooltip.style.top = top + 'px';
}

/**
 * Скрыть tooltip
 */
function hideSummaryTooltip() {
    const tooltip = document.getElementById('summary-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

/**
 * Экранирование HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
