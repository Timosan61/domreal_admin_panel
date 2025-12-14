/**
 * JavaScript для страницы списка звонков
 */

// Глобальные переменные
let currentPage = 1;
let currentFilters = {};
let currentSort = { by: 'started_at_utc', order: 'DESC' };
let multiselectInstances = null; // Хранилище для multiselect инстансов

// Глобальный аудиоплеер
let globalWaveSurfer = null;
let currentPlayingCallId = null;

// Динамические шаблоны чеклистов
let activeTemplates = []; // Список активных шаблонов
let complianceDataCache = {}; // Кэш compliance данных по звонкам
let alertFlagsCache = {}; // Кэш тревожных флагов конфликта интересов

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
    const filterKeys = ['call_type', 'date_from', 'date_to', 'search', 'client_phone', 'call_id', 'duration_range', 'hide_short_calls'];

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

        // CRM этапы
        const crmStages = params.get('crm_stages');
        if (crmStages) {
            currentFilters['crm_stages'] = crmStages;
            const crmMS = multiselectInstances.get('crm-stages-multiselect');
            if (crmMS) {
                const crmArray = crmStages.split(',');
                crmMS.setValues(crmArray);
            }
        }

        // Платежеспособность
        const solvencyLevels = params.get('solvency_levels');
        if (solvencyLevels) {
            currentFilters['solvency_levels'] = solvencyLevels;
            const solvencyMS = multiselectInstances.get('solvency-multiselect');
            if (solvencyMS) {
                const solvencyArray = solvencyLevels.split(',');
                solvencyMS.setValues(solvencyArray);
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

    // Проверяем, пришли ли мы из аналитики
    const fromAnalytics = params.get('from_analytics');
    if (fromAnalytics === '1') {
        showAnalyticsBreadcrumb();
    }
}

/**
 * Показать breadcrumb для возврата к аналитике
 */
function showAnalyticsBreadcrumb() {
    const breadcrumb = document.getElementById('analytics-breadcrumb');
    if (breadcrumb) {
        breadcrumb.style.display = 'block';
        console.log('✅ Breadcrumb показан - пользователь пришел из аналитики');
    }
}

/**
 * Инициализация страницы
 */
async function initializePage() {
    // Инициализируем multiselect компоненты
    multiselectInstances = initMultiselects();

    // 🎯 Загружаем активные шаблоны чеклистов
    await loadActiveTemplates();

    await loadFilterOptions();
    await loadStateFromURL(); // Восстанавливаем состояние из URL (теперь async)
    setupEventListeners();
    initGlobalAudioPlayer(); // Инициализация глобального плеера
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

            // Заполняем multiselect тегов
            const tagsMS = multiselectInstances.get('tags-multiselect');
            if (tagsMS) {
                const tagOptions = [
                    { name: 'tags[]', value: 'good', label: '✅ Хорошо' },
                    { name: 'tags[]', value: 'bad', label: '❌ Плохо' },
                    { name: 'tags[]', value: 'question', label: '❓ Вопрос' }
                ];
                tagsMS.setOptions(tagOptions);
            }

            // Загружаем CRM этапы
            const crmResponse = await fetch('api/crm_stages.php');
            const crmData = await crmResponse.json();
            if (crmData.success && crmData.data) {
                const crmMS = multiselectInstances.get('crm-stages-multiselect');
                if (crmMS) {
                    const crmOptions = crmData.data.map(stage => ({
                        name: 'crm_stages[]',
                        value: `${stage.crm_funnel_name}:${stage.crm_step_name}`,
                        label: `${stage.crm_funnel_name} → ${stage.crm_step_name}`
                    }));
                    crmMS.setOptions(crmOptions);
                }
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

        // Восстанавливаем toggle в состояние "включен"
        const hideShortCallsCheckbox = document.getElementById('hide-short-calls');
        if (hideShortCallsCheckbox) {
            hideShortCallsCheckbox.checked = true;
        }

        currentPage = 1;
        currentFilters = {};
        saveStateToURL(); // Сохраняем состояние в URL
        loadManagersByDepartments([]); // Загружаем всех менеджеров
        loadCalls();
    });

    // Обработчик toggle "Скрыть до 10 сек"
    const hideShortCallsCheckbox = document.getElementById('hide-short-calls');
    if (hideShortCallsCheckbox) {
        hideShortCallsCheckbox.addEventListener('change', function() {
            currentPage = 1;
            currentFilters = getFiltersFromForm();
            saveStateToURL();
            loadCalls();
        });
    }

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

    // Обработка toggle "Скрыть до 10 сек" (checkbox всегда передается, даже если unchecked)
    const hideShortCallsCheckbox = document.getElementById('hide-short-calls');
    if (hideShortCallsCheckbox) {
        filters['hide_short_calls'] = hideShortCallsCheckbox.checked ? '1' : '0';
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

        // CRM этапы
        const crmMS = multiselectInstances.get('crm-stages-multiselect');
        if (crmMS) {
            const crmStages = crmMS.getValues();
            if (crmStages.length > 0) {
                filters['crm_stages'] = crmStages.join(',');
            }
        }

        // Платежеспособность
        const solvencyMS = multiselectInstances.get('solvency-multiselect');
        if (solvencyMS) {
            const solvencyLevels = solvencyMS.getValues();
            if (solvencyLevels.length > 0) {
                filters['solvency_levels'] = solvencyLevels.join(',');
            }
        }

        // Статус клиента
        const clientStatusMS = multiselectInstances.get('client-status-multiselect');
        if (clientStatusMS) {
            const clientStatuses = clientStatusMS.getValues();
            if (clientStatuses.length > 0) {
                filters['client_statuses'] = clientStatuses.join(',');
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
    tbody.innerHTML = '<tr><td colspan="18" class="loading">Загрузка данных...</td></tr>';

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

        // Добавляем timeout для предотвращения зависания
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            controller.abort();
            console.error('⏱️ Timeout: запрос превысил 30 секунд');
        }, 30000); // 30 секунд

        const response = await fetch(`api/calls.php?${params}`, {
            signal: controller.signal
        });

        // Очистить таймер после успешного ответа
        clearTimeout(timeoutId);

        const result = await response.json();

        if (result.success) {
            await renderCalls(result.data); // Теперь асинхронно
            renderPagination(result.pagination);
            updateStats(result.pagination);
        } else {
            tbody.innerHTML = '<tr><td colspan="18" class="error">Ошибка загрузки данных</td></tr>';
        }
    } catch (error) {
        console.error('Ошибка загрузки звонков:', error);

        if (error.name === 'AbortError') {
            tbody.innerHTML = '<tr><td colspan="18" class="error">⏱️ Превышено время ожидания (30 сек). Попробуйте упростить фильтры или обратитесь к администратору.</td></tr>';
        } else {
            tbody.innerHTML = '<tr><td colspan="18" class="error">Ошибка подключения к серверу</td></tr>';
        }
    }
}

/**
 * Отрисовка списка звонков
 */
async function renderCalls(calls) {
    const tbody = document.getElementById('calls-tbody');

    if (calls.length === 0) {
        tbody.innerHTML = '<tr><td colspan="18" class="text-center">Звонки не найдены</td></tr>';
        return;
    }

    // 🎯 Загружаем compliance данные и alert flags для всех звонков
    const callIds = calls.map(call => call.callid);
    await Promise.all([
        loadComplianceData(callIds),
        loadAlertFlags(callIds)
    ]);

    // Получаем текущий URL со state для передачи в страницу деталей
    const currentStateURL = window.location.search;

    tbody.innerHTML = calls.map(call => `
        <tr>
            <td class="text-center" data-column-id="checkbox">
                <input type="checkbox" class="call-checkbox" data-callid="${call.callid}">
            </td>
            <td class="tag-cell ${!call.tag_type ? 'no-tag' : ''}" data-column-id="tag" title="${formatTagTitle(call.tag_type, call.tag_note)}">
                ${formatTag(call.tag_type)}
            </td>
            <td class="employee-cell" data-column-id="manager" data-full-text="${escapeHtml(call.employee_name || '-')}">${formatEmployeeName(call.employee_name)}</td>
            <td data-column-id="result">${formatCallResult(call.client_overall_status || call.call_result, call.is_successful, call.call_type)}</td>
            ${renderComplianceCells(call.callid, call)}
            <td class="summary-cell" data-column-id="summary" data-full-text="${escapeHtml(call.summary_text || '')}">${formatSummary(call.summary_text)}</td>
            <td class="solvency-cell" data-column-id="solvency">${formatSolvency(call.solvency_level)}</td>
            <td data-column-id="datetime">${formatDateTime(call.started_at_utc)}</td>
            <td class="text-center" data-column-id="duration">${formatDuration(call.duration_sec)}</td>
            <td data-column-id="phone">${escapeHtml(call.client_phone || '-')}</td>
            <td class="crm-cell" data-column-id="crm">${formatCrmStage(call.crm_funnel_name, call.crm_step_name)}</td>
            <td class="actions-cell" data-column-id="actions">
                <button class="btn-play-audio ${currentPlayingCallId === call.callid ? 'playing' : ''}"
                        data-callid="${call.callid}"
                        data-employee="${escapeHtml(call.employee_name || '')}"
                        data-client="${escapeHtml(call.client_phone || '')}"
                        title="Проиграть запись"
                        ${!call.audio_path && !call.audio_status ? 'disabled' : ''}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                </button>
                <a href="call_evaluation.php?callid=${encodeURIComponent(call.callid)}&returnState=${encodeURIComponent(currentStateURL)}"
                   class="btn btn-primary btn-sm">
                    Открыть
                </a>
            </td>
            <td data-column-id="call_type">${formatCallType(call.call_type, call.is_first_call, call.duration_sec)}</td>
            <td class="department-cell" data-column-id="department" data-full-text="${escapeHtml(call.department || '-')}">${formatDepartment(call.department)}</td>
            <td data-column-id="direction">${formatDirection(call.direction)}</td>
        </tr>
    `).join('');

    // Инициализация tooltip для обрезанных ячеек
    initTruncatedCellTooltips('.employee-cell');
    initTruncatedCellTooltips('.summary-cell');
    initTruncatedCellTooltips('.aggregate-cell');
    initTruncatedCellTooltips('.department-cell');
    initTruncatedCellTooltips('.compliance-column.summary-cell'); // Tooltip для ячеек шаблонов

    // Инициализация обработчиков для кнопок Play
    initPlayButtons();
}

/**
 * Форматирование тега
 */
function formatTag(tagType) {
    const tagEmojis = {
        'good': '✅',
        'bad': '❌',
        'question': '❓',
        'problem': '⚠️'
    };
    return tagEmojis[tagType] || '—';
}

/**
 * Форматирование title для тега
 */
function formatTagTitle(tagType, tagNote) {
    if (!tagType) return 'Без тега';

    const tagNames = {
        'good': 'Хорошо',
        'bad': 'Плохо',
        'question': 'Вопрос',
        'problem': 'Проблемный'
    };

    let title = `Тег: ${tagNames[tagType]}`;
    if (tagNote) {
        title += `\nЗаметка: ${tagNote}`;
    }
    return title;
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
        'INBOUND': '<span class="badge badge-info badge-nowrap">Входящий</span>',
        'OUTBOUND': '<span class="badge badge-success badge-nowrap">Исходящий</span>',
        'MISSED': '<span class="badge badge-danger badge-nowrap">Пропущенный</span>'
    };
    return directions[direction] || `<span class="badge badge-nowrap">${escapeHtml(direction)}</span>`;
}

/**
 * Форматирование типа звонка (первичный/повторный/несостоявшийся)
 *
 * @param {string} type - Тип из БД (не используется, оставлен для совместимости)
 * @param {number|boolean} isFirstCall - Флаг первого звонка из calls_raw
 * @param {number} durationSec - Длительность звонка в секундах
 */
function formatCallType(type, isFirstCall, durationSec) {
    // ✨ НОВАЯ ЛОГИКА (2025-11-03): 3 типа звонков
    // 1. Несостоявшийся (≤30 сек) - любые короткие звонки
    // 2. Первичный (>30 сек + is_first_call=1)
    // 3. Повторный (>30 сек + is_first_call=0)

    // Определяем тип по длительности и флагу
    if (durationSec !== undefined && durationSec !== null && durationSec <= 30) {
        return '<span class="badge badge-warning badge-nowrap">⏱️ Несостоявшийся</span>';
    } else if (isFirstCall === 1 || isFirstCall === true) {
        return '<span class="badge badge-info badge-nowrap">1️⃣ Первичный</span>';
    } else if (isFirstCall === 0 || isFirstCall === false) {
        return '<span class="badge badge-secondary badge-nowrap">🔁 Повторный</span>';
    }

    // Fallback (если данные недоступны)
    return '<span class="badge badge-secondary badge-nowrap">—</span>';
}

/**
 * Форматирование оценки выполнения скрипта (script_compliance_score от 0.00 до 1.00)
 *
 * ВАЖНО: С версии v4 скрипт работает для ВСЕХ типов звонков (first_call и repeat_call)
 */
function formatScriptCompliance(score, callType) {
    // Если оценка отсутствует - показываем н/д
    if (score === null || score === undefined) {
        return '<span class="text-muted">н/д</span>';
    }

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
 * Форматирование процента соответствия чеклисту (compliance_percentage от 0 до 100)
 * Отображает % положительных ответов из динамических шаблонов анализа
 */
function formatCompliancePercentage(percentage) {
    // Если данных нет - показываем н/д
    if (percentage === null || percentage === undefined) {
        return '<span class="text-muted">н/д</span>';
    }

    const percent = parseInt(percentage);
    let className = 'rating-low';

    if (percent >= 80) {
        className = 'rating-high';
    } else if (percent >= 60) {
        className = 'rating-medium';
    }

    return `<span class="rating-badge ${className}">${percent}%</span>`;
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
 * Стандартизированные результаты (2025-10-29)
 * Логика совпадает с детальной страницей (call_evaluation.js)
 */
function formatCallResult(result, isSuccessful, callType) {
    // Пытаемся использовать имеющиеся данные
    if (!result && isSuccessful === null) return '-';

    // Если есть результат, отображаем его
    if (result) {
        // Очищаем префикс "Результат:" если есть
        let cleanResult = result.replace(/^Результат:\s*/i, '').trim();

        // Убираем лишние слова для компактности
        cleanResult = cleanResult.replace(/\s+звонок$/i, '');
        cleanResult = cleanResult.replace(/\s+выполнена$/i, '');

        let badgeClass = 'badge-info'; // По умолчанию синий
        let icon = '';
        const resultLower = cleanResult.toLowerCase();

        // ✅ Стандартизированные результаты (работают для обоих типов звонков)

        // 🟢 Позитивные результаты (зеленые)
        if (resultLower.includes('назначен показ')) {
            badgeClass = 'badge-success';
            icon = '📅 ';
        } else if (resultLower.includes('подтвержден показ') || resultLower.includes('подтверждён показ')) {
            badgeClass = 'badge-success';
            icon = '✅ ';
        } else if (resultLower.includes('показ проведен') || resultLower.includes('показ провед')) {
            badgeClass = 'badge-success';
            icon = '🏠 ';
        } else if (resultLower.includes('отправлены новые варианты') || (resultLower.includes('отправлен') && resultLower.includes('вариант'))) {
            badgeClass = 'badge-success';
            icon = '📤 ';
        } else if (resultLower.includes('клиент подтвердил интерес')) {
            badgeClass = 'badge-success';
            icon = '👍 ';
        } else if (resultLower.includes('бронь') || resultLower.includes('задаток')) {
            badgeClass = 'badge-success';
            icon = '💰 ';
        } else if (resultLower.includes('сделка закрыта') || resultLower.includes('сделка заверш')) {
            badgeClass = 'badge-success';
            icon = '🎉 ';
        } else if (resultLower.includes('назначена консультация')) {
            badgeClass = 'badge-success';
            icon = '🗓️ ';
        }

        // 🟡 Нейтральные/Ожидание (желтые/синие)
        else if (resultLower.includes('отложенное решение') || resultLower.includes('отложен')) {
            badgeClass = 'badge-info';
            icon = '⏳ ';
        } else if (resultLower.includes('ожидается ответ клиента') || (resultLower.includes('ожидается') && resultLower.includes('ответ'))) {
            badgeClass = 'badge-info';
            icon = '⏰ ';
        }

        // 🔴 Негативные (красные/серые)
        else if (resultLower.includes('недозвон') || resultLower.includes('не дозвон') || resultLower.includes('не отвечает')) {
            badgeClass = 'badge-secondary';
            icon = '📵 ';
        } else if (resultLower.includes('отказ') || resultLower.includes('неактуально')) {
            badgeClass = 'badge-danger';
            icon = '❌ ';
        } else if (resultLower.includes('не целевой') || resultLower.includes('нецелевой')) {
            badgeClass = 'badge-danger';
            icon = '🚫 ';
        }

        // 🔵 Fallback для старых результатов
        else if (resultLower.includes('квалифик')) {
            badgeClass = 'badge-success';
            icon = '📋 ';
        } else if (resultLower.includes('показ') || resultLower.includes('презентац')) {
            badgeClass = 'badge-success';
            icon = '🏠 ';
        } else if (resultLower.includes('материал')) {
            badgeClass = 'badge-success';
            icon = '📤 ';
        } else if (resultLower.includes('перезвон')) {
            badgeClass = 'badge-warning';
            icon = '📞 ';
        } else if (resultLower.includes('думает')) {
            badgeClass = 'badge-info';
            icon = '💭 ';
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
 * Форматирование агрегированного анализа клиента с обрезкой текста
 */
function formatAggregate(aggregateText, totalCalls) {
    if (!aggregateText || aggregateText.trim() === '') {
        // Если нет агрегированного анализа, показываем просто количество звонков
        if (totalCalls && totalCalls > 0) {
            return `<span class="text-muted" style="font-size: 0.9em;">${totalCalls} звонков</span>`;
        }
        return '-';
    }

    const maxLength = 40;
    const text = aggregateText.trim();

    // Добавляем метку с количеством звонков если есть
    let prefix = '';
    if (totalCalls && totalCalls > 1) {
        prefix = `<span class="badge badge-info" style="font-size: 0.75em; margin-right: 4px;">${totalCalls}</span>`;
    }

    if (text.length > maxLength) {
        return prefix + escapeHtml(text.substring(0, maxLength)) + '...';
    }

    return prefix + escapeHtml(text);
}

/**
 * Форматирование имени менеджера с обрезкой текста
 */
function formatEmployeeName(employeeName) {
    if (!employeeName || employeeName.trim() === '') return '-';

    const maxLength = 25;
    const text = employeeName.trim();

    if (text.length > maxLength) {
        return escapeHtml(text.substring(0, maxLength)) + '...';
    }

    return escapeHtml(text);
}

/**
 * Форматирование названия отдела с обрезкой текста
 */
function formatDepartment(department) {
    if (!department || department.trim() === '') return '-';

    const maxLength = 30;
    const text = department.trim();

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
 * УНИВЕРСАЛЬНАЯ функция инициализации tooltip для обрезанных ячеек
 * @param {string} selector - CSS селектор ячеек (например, '.summary-cell', '.employee-cell')
 */
function initTruncatedCellTooltips(selector) {
    const cells = document.querySelectorAll(selector);

    cells.forEach(cell => {
        const fullText = cell.getAttribute('data-full-text');

        // Показываем tooltip если текст обрезан CSS (scrollWidth) или есть полный текст отличный от отображаемого
        if (fullText && fullText.trim() !== '' && fullText.trim() !== '-') {
            const isTruncatedByCSS = cell.scrollWidth > cell.clientWidth;
            const displayedText = cell.textContent.trim();
            const isTruncated = isTruncatedByCSS || fullText !== displayedText;

            if (isTruncated) {
                cell.addEventListener('mouseenter', function(e) {
                    showTruncatedTooltip(e, fullText);
                });

                cell.addEventListener('mouseleave', function() {
                    hideTruncatedTooltip();
                });

                // Добавляем курсор pointer для индикации интерактивности
                cell.style.cursor = 'pointer';
            }
        }
    });
}

/**
 * Показать tooltip с полным текстом
 */
function showTruncatedTooltip(event, text) {
    // Удаляем существующий tooltip если есть
    hideTruncatedTooltip();

    const tooltip = document.createElement('div');
    tooltip.className = 'truncated-tooltip';
    tooltip.textContent = text;
    tooltip.id = 'truncated-tooltip';

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
function hideTruncatedTooltip() {
    const tooltip = document.getElementById('truncated-tooltip');
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

/**
 * Форматирование CRM воронки и этапа
 */
function formatCrmStage(funnelName, stepName) {
    if (!funnelName || !stepName) {
        return '<span class="badge badge-secondary">Нет данных</span>';
    }

    // Цветовая кодировка по воронкам
    const colors = {
        'Покупатели': 'success',
        'Продавец': 'info',
        'Риелторы': 'warning'
    };

    const color = colors[funnelName] || 'secondary';

    return `
        <span class="badge badge-${color}">
            ${escapeHtml(funnelName)}
        </span>
        <br>
        <small class="text-muted">${escapeHtml(stepName)}</small>
    `;
}

/**
 * Форматирование платежеспособности клиента
 */
function formatSolvency(solvencyLevel) {
    if (!solvencyLevel) {
        return '<span class="text-muted">—</span>';
    }

    const badges = {
        'green': { text: '🟢 Высокая', class: 'success' },
        'blue': { text: '🔵 Средняя', class: 'info' },
        'yellow': { text: '🟡 Низкая', class: 'warning' },
        'red': { text: '🔴 Очень низкая', class: 'danger' }
    };

    const badge = badges[solvencyLevel] || { text: solvencyLevel, class: 'secondary' };
    return `<span class="badge badge-${badge.class}">${badge.text}</span>`;
}

/* ========================================
   Глобальный аудиоплеер
   ======================================== */

/**
 * Инициализация глобального аудиоплеера
 */
function initGlobalAudioPlayer() {
    // Проверка наличия WaveSurfer
    if (typeof WaveSurfer === 'undefined') {
        console.error('WaveSurfer.js не загружен');
        return;
    }

    // Создание экземпляра WaveSurfer
    globalWaveSurfer = WaveSurfer.create({
        container: '#global-waveform',
        waveColor: '#ddd',
        progressColor: '#007AFF',
        cursorColor: '#007AFF',
        barWidth: 2,
        barRadius: 3,
        responsive: true,
        height: 60,
        normalize: true,
        backend: 'WebAudio'
    });

    // Обработчики событий WaveSurfer
    globalWaveSurfer.on('ready', function() {
        const duration = globalWaveSurfer.getDuration();
        document.getElementById('player-total-time').textContent = formatTime(duration);
    });

    globalWaveSurfer.on('audioprocess', function() {
        const currentTime = globalWaveSurfer.getCurrentTime();
        document.getElementById('player-current-time').textContent = formatTime(currentTime);
    });

    globalWaveSurfer.on('finish', function() {
        updatePlayPauseButton(false);
    });

    // Обработчик кнопки Play/Pause
    document.getElementById('global-play-btn').addEventListener('click', function() {
        if (globalWaveSurfer) {
            globalWaveSurfer.playPause();
            updatePlayPauseButton(globalWaveSurfer.isPlaying());
        }
    });

    // Обработчик регулятора громкости
    document.getElementById('volume-slider').addEventListener('input', function() {
        if (globalWaveSurfer) {
            globalWaveSurfer.setVolume(this.value / 100);
        }
    });

    // Обработчик скорости воспроизведения
    document.getElementById('global-speed').addEventListener('change', function() {
        if (globalWaveSurfer) {
            globalWaveSurfer.setPlaybackRate(parseFloat(this.value));
        }
    });

    // Обработчик кнопки закрытия плеера
    document.getElementById('player-close-btn').addEventListener('click', function() {
        closeGlobalPlayer();
    });
}

/**
 * Инициализация обработчиков для кнопок Play в таблице
 */
function initPlayButtons() {
    const playButtons = document.querySelectorAll('.btn-play-audio');

    playButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const callid = this.getAttribute('data-callid');
            const employee = this.getAttribute('data-employee');
            const client = this.getAttribute('data-client');

            playAudioInGlobalPlayer(callid, employee, client);
        });
    });
}

/**
 * Проигрывание аудио в глобальном плеере
 */
function playAudioInGlobalPlayer(callid, employeeName, clientPhone) {
    if (!globalWaveSurfer) {
        console.error('Глобальный плеер не инициализирован');
        return;
    }

    // Показываем плеер
    const playerElement = document.getElementById('global-audio-player');
    playerElement.style.display = 'block';

    // Обновляем информацию о звонке
    document.getElementById('player-callid').textContent = callid;
    document.getElementById('player-employee').textContent = employeeName || '-';
    document.getElementById('player-client').textContent = clientPhone || '-';

    // Загружаем аудио
    const audioUrl = `api/audio_stream.php?callid=${encodeURIComponent(callid)}`;

    globalWaveSurfer.load(audioUrl);
    currentPlayingCallId = callid;

    // Обновляем состояние кнопок Play в таблице
    updatePlayButtonsState();

    // Автовоспроизведение при загрузке
    globalWaveSurfer.on('ready', function() {
        globalWaveSurfer.play();
        updatePlayPauseButton(true);
    }, { once: true });
}

/**
 * Обновление состояния кнопки Play/Pause
 */
function updatePlayPauseButton(isPlaying) {
    const playBtn = document.getElementById('global-play-btn');

    if (isPlaying) {
        // Иконка Pause
        playBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
        `;
    } else {
        // Иконка Play
        playBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
        `;
    }
}

/**
 * Обновление состояния кнопок Play в таблице
 */
function updatePlayButtonsState() {
    const playButtons = document.querySelectorAll('.btn-play-audio');

    playButtons.forEach(button => {
        const callid = button.getAttribute('data-callid');

        if (callid === currentPlayingCallId) {
            button.classList.add('playing');
        } else {
            button.classList.remove('playing');
        }
    });
}

/**
 * Закрытие глобального плеера
 */
function closeGlobalPlayer() {
    if (globalWaveSurfer) {
        globalWaveSurfer.stop();
    }

    document.getElementById('global-audio-player').style.display = 'none';
    currentPlayingCallId = null;
    updatePlayButtonsState();
}

/**
 * Форматирование времени (секунды -> мм:сс)
 */
function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';

    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return minutes + ':' + (secs < 10 ? '0' : '') + secs;
}

// ═══════════════════════════════════════════════════════════════
// 🎯 ДИНАМИЧЕСКИЕ ШАБЛОНЫ ЧЕКЛИСТОВ
// ═══════════════════════════════════════════════════════════════

/**
 * Загрузка активных шаблонов чеклистов
 */
async function loadActiveTemplates() {
    try {
        console.log('🔄 Загрузка активных шаблонов...');
        const response = await fetch('api/active_templates.php');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        console.log('📥 Получен ответ от API:', result);

        if (result.templates && Array.isArray(result.templates)) {
            activeTemplates = result.templates;
            console.log(`✅ Загружено ${activeTemplates.length} активных шаблонов:`, activeTemplates);

            // Добавляем колонки в таблицу
            insertComplianceColumns();
        } else {
            console.warn('⚠️ Не удалось загрузить активные шаблоны - неверный формат ответа');
            activeTemplates = [];
        }
    } catch (error) {
        console.error('❌ Ошибка при загрузке активных шаблонов:', error);

        // FALLBACK: Используем хардкодные шаблоны для тестирования
        activeTemplates = [
            {template_id: 'tpl-e6ee988fce03', name: 'недвижимость (v4)', template_type: 'first_call'},
            {template_id: 'tpl-deal-dynamics-v1', name: 'Динамика сделки (унифицированный)', template_type: 'custom'}
        ];
        console.log('⚠️ Используем fallback шаблоны:', activeTemplates);
        insertComplianceColumns();
    }
}

/**
 * Динамическая вставка колонок для чеклистов в заголовок таблицы
 */
function insertComplianceColumns() {
    const placeholder = document.getElementById('compliance-headers-placeholder');
    if (!placeholder) {
        console.error('❌ Не найден placeholder compliance-headers-placeholder');
        return;
    }

    // Удаляем старые колонки (если есть)
    const oldColumns = document.querySelectorAll('.compliance-column-header');
    oldColumns.forEach(col => col.remove());

    // Если нет активных шаблонов, скрываем placeholder
    if (activeTemplates.length === 0) {
        placeholder.style.display = 'none';
        return;
    }

    // Находим заголовок "Резюме" для вставки перед ним
    const headerRow = document.querySelector('thead tr');
    const allHeaders = Array.from(headerRow.querySelectorAll('th'));
    const insertBeforeElement = allHeaders.find(th => th.textContent.trim().includes('Резюме'));

    console.log(`🔍 Найден заголовок "Резюме":`, insertBeforeElement);

    // Удаляем placeholder
    placeholder.remove();

    // Создаём и вставляем заголовки для каждого шаблона
    activeTemplates.forEach(template => {
        const th = document.createElement('th');
        th.className = 'compliance-column compliance-column-header';
        th.setAttribute('data-template-id', template.template_id);
        th.title = template.name; // Полное название в tooltip

        // Сокращаем название для отображения
        const shortName = shortenTemplateName(template.name);
        th.innerHTML = `${shortName} <span class="sort-icon">↕</span>`;

        // Вставляем колонку перед "Резюме"
        if (insertBeforeElement) {
            headerRow.insertBefore(th, insertBeforeElement);
            console.log(`✅ Вставлена колонка "${shortName}" перед "Резюме"`);
        } else {
            headerRow.appendChild(th);
            console.warn(`⚠️ Заголовок "Резюме" не найден, колонка "${shortName}" добавлена в конец`);
        }
    });

    console.log(`✅ Добавлено ${activeTemplates.length} колонок чеклистов`);
}

/**
 * Сокращение названия шаблона для отображения в заголовке
 */
function shortenTemplateName(name) {
    // Удаляем версии и скобки
    let short = name.replace(/\(v\d+\)/gi, '').replace(/\([^)]+\)/g, '').trim();

    // Если название слишком длинное, обрезаем
    if (short.length > 20) {
        short = short.substring(0, 18) + '...';
    }

    return short;
}

/**
 * Загрузка compliance данных для списка звонков (bulk)
 */
async function loadComplianceData(callIds) {
    if (!callIds || callIds.length === 0) {
        return {};
    }

    try {
        const response = await fetch('http://localhost:8000/api/compliance/by-calls', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(callIds)
        });

        const result = await response.json();

        if (result.compliance_by_call) {
            // Обновляем кэш
            complianceDataCache = { ...complianceDataCache, ...result.compliance_by_call };
            console.log(`✅ Загружено compliance для ${Object.keys(result.compliance_by_call).length} звонков`);
            return result.compliance_by_call;
        }

        return {};
    } catch (error) {
        console.error('❌ Ошибка при загрузке compliance данных:', error);
        return {};
    }
}

/**
 * Получение compliance % для конкретного звонка и шаблона
 */
function getComplianceForCall(callid, templateId) {
    if (!complianceDataCache[callid]) {
        return null;
    }

    return complianceDataCache[callid][templateId] !== undefined
        ? complianceDataCache[callid][templateId]
        : null;
}

/**
 * Форматирование compliance % для отображения в ячейке
 */
function formatComplianceCell(compliance) {
    if (compliance === null || compliance === undefined) {
        return '<span class="compliance-na">—</span>';
    }

    let cssClass = 'compliance-low';
    if (compliance >= 80) {
        cssClass = 'compliance-high';
    } else if (compliance >= 50) {
        cssClass = 'compliance-medium';
    }

    return `<span class="compliance-value ${cssClass}">${compliance}%</span>`;
}

/**
 * Форматирование результата шаблона:
 * - Динамика сделки: стрелочка + текстовый анализ
 * - Остальные шаблоны: только процент выполнения (ДА/НЕТ вопросы)
 */
function formatTemplateSummary(summaryText, complianceScore, templateId) {
    // Шаблон "Динамика сделки" - показываем стрелочку + текст
    if (templateId === 'tpl-deal-dynamics-v1') {
        if (!summaryText || summaryText.trim() === '') {
            return '<span class="compliance-na">—</span>';
        }

        // Обрезаем текст до 100 символов
        const truncated = summaryText.length > 100
            ? summaryText.substring(0, 100) + '...'
            : summaryText;

        // Определяем динамику по ключевым словам в тексте (не по проценту!)
        const text = summaryText.toLowerCase();
        const isPositive =
            text.includes('прогресс') ||
            text.includes('договорились') ||
            text.includes('интерес') ||
            text.includes('согласился') ||
            text.includes('запланирован') ||
            text.includes('ждёт') ||
            text.includes('готов');

        const isNegative =
            text.includes('не состоялся') ||
            text.includes('не ответил') ||
            text.includes('сбросил') ||
            text.includes('отказ') ||
            text.includes('не интересует') ||
            text.includes('передумал');

        let arrowHtml = '';
        if (isPositive && !isNegative) {
            arrowHtml = `<span class="dynamics-arrow dynamics-positive" title="Положительная динамика">↗</span> `;
        } else if (isNegative) {
            arrowHtml = `<span class="dynamics-arrow dynamics-negative" title="Отрицательная динамика">↘</span> `;
        } else {
            // Нейтральная динамика - без стрелки
            arrowHtml = `<span class="dynamics-arrow" title="Нейтральная динамика" style="color: #94a3b8;">→</span> `;
        }

        return `${arrowHtml}${escapeHtml(truncated)}`;
    }

    // Все остальные шаблоны - показываем ТОЛЬКО процент
    if (complianceScore === null || complianceScore === undefined) {
        return '<span class="compliance-na">—</span>';
    }

    // Для "Конфликта интересов" логика ИНВЕРТИРОВАНА:
    // Больше % = хуже (красный), меньше % = лучше (зеленый)
    let cssClass = 'compliance-low';
    if (templateId === 'tpl-conflict-of-interest-v1') {
        // Инвертированная логика для конфликта интересов
        if (complianceScore <= 20) {
            cssClass = 'compliance-high';  // Мало конфликта - зеленый
        } else if (complianceScore <= 50) {
            cssClass = 'compliance-medium'; // Средний конфликт - желтый
        } else {
            cssClass = 'compliance-low';    // Высокий конфликт - красный
        }
    } else {
        // Обычная логика для остальных шаблонов
        if (complianceScore >= 80) {
            cssClass = 'compliance-high';
        } else if (complianceScore >= 50) {
            cssClass = 'compliance-medium';
        }
    }

    return `<span class="compliance-value ${cssClass}">${complianceScore}%</span>`;
}

/**
 * Рендеринг всех ячеек compliance для одного звонка
 */
function renderComplianceCells(callid, call) {
    if (activeTemplates.length === 0) {
        console.warn(`⚠️ renderComplianceCells: нет активных шаблонов для звонка ${callid}`);
        return ''; // Нет активных шаблонов
    }

    // Логируем данные звонка для отладки (только для первых 3 звонков)
    if (window.debugCallCount === undefined) window.debugCallCount = 0;
    if (window.debugCallCount < 3) {
        console.log(`🔍 renderComplianceCells для звонка ${callid}:`, {
            template_id: call?.template_id,
            compliance_score: call?.compliance_score,
            activeTemplatesCount: activeTemplates.length
        });
        window.debugCallCount++;
    }

    // Рендерим ячейку для каждого активного шаблона
    return activeTemplates.map(template => {
        // Если у звонка template_id совпадает с текущим шаблоном, показываем его данные
        if (call && call.template_id === template.template_id) {
            // Показываем краткое резюме анализа (как в Агрегированном анализе)
            const summaryText = call.summary_text || '';
            const complianceScore = call.compliance_score !== null ? call.compliance_score : null;

            return `<td class="summary-cell compliance-column" data-template-id="${template.template_id}" data-full-text="${escapeHtml(summaryText)}">
                ${formatTemplateSummary(summaryText, complianceScore, template.template_id)}
            </td>`;
        } else {
            // Для этого звонка использовался другой шаблон - показываем пусто
            return `<td class="text-center compliance-column" data-template-id="${template.template_id}">
                <span class="compliance-na">—</span>
            </td>`;
        }
    }).join('');
}

// ═══════════════════════════════════════════════════════════════
// ТРЕВОЖНЫЕ ФЛАГИ КОНФЛИКТА ИНТЕРЕСОВ
// ═══════════════════════════════════════════════════════════════

/**
 * Загрузка тревожных флагов для звонков
 */
async function loadAlertFlags(callIds) {
    if (!callIds || callIds.length === 0) {
        return {};
    }

    try {
        const callidsParam = callIds.join(',');
        const response = await fetch(`api/alert_flags.php?callids=${encodeURIComponent(callidsParam)}`);
        const result = await response.json();

        if (result.success && result.data) {
            // Обновляем кэш
            alertFlagsCache = { ...alertFlagsCache, ...result.data };
            console.log(`✅ Загружено alert flags для ${Object.keys(result.data).length} звонков`);
            return result.data;
        }

        return {};
    } catch (error) {
        console.error('❌ Ошибка при загрузке alert flags:', error);
        return {};
    }
}

/**
 * Получение alert flags для конкретного звонка
 */
function getAlertFlagsForCall(callid) {
    return alertFlagsCache[callid] || null;
}

/**
 * Форматирование alert level для отображения в ячейке
 */
function formatAlertLevel(callid) {
    const flags = getAlertFlagsForCall(callid);

    if (!flags || flags.total_flags === 0) {
        return '<span class="alert-none" style="color: #9ca3af;">—</span>';
    }

    const alertLevel = flags.alert_level;
    const totalFlags = flags.total_flags;

    // Emoji и цвет в зависимости от уровня
    const levelConfig = {
        'CRITICAL': {
            emoji: '🔴',
            color: '#dc2626',
            bgColor: '#fee2e2',
            text: 'КРИТИЧЕСКИЙ'
        },
        'HIGH': {
            emoji: '🟠',
            color: '#ea580c',
            bgColor: '#ffedd5',
            text: 'ВЫСОКИЙ'
        },
        'MEDIUM': {
            emoji: '🟡',
            color: '#ca8a04',
            bgColor: '#fef3c7',
            text: 'СРЕДНИЙ'
        },
        'LOW': {
            emoji: '🟢',
            color: '#16a34a',
            bgColor: '#dcfce7',
            text: 'НИЗКИЙ'
        }
    };

    const config = levelConfig[alertLevel] || levelConfig['LOW'];

    // Формируем title с деталями
    let title = `Уровень тревоги: ${config.text}\\n`;
    title += `Всего флагов: ${totalFlags}\\n`;
    if (flags.critical_flags > 0) title += `🔴 Критических: ${flags.critical_flags}\\n`;
    if (flags.high_flags > 0) title += `🟠 Высоких: ${flags.high_flags}\\n`;
    if (flags.medium_flags > 0) title += `🟡 Средних: ${flags.medium_flags}\\n`;
    if (flags.low_flags > 0) title += `🟢 Низких: ${flags.low_flags}\\n`;
    if (flags.scenarios) {
        title += `\\nСценарии:\\n${flags.scenarios}`;
    }

    return `
        <span class="alert-badge alert-${alertLevel.toLowerCase()}"
              style="
                  display: inline-flex;
                  align-items: center;
                  gap: 4px;
                  padding: 4px 10px;
                  border-radius: 12px;
                  font-size: 12px;
                  font-weight: 600;
                  color: ${config.color};
                  background-color: ${config.bgColor};
                  border: 1px solid ${config.color}33;
                  cursor: help;
              "
              title="${title}">
            ${config.emoji} ${totalFlags}
        </span>
    `;
}
