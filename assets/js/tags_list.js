/**
 * JavaScript для страницы тегированных звонков
 */

// Глобальные переменные
let currentPage = 1;
let currentFilters = {};
let currentSort = { by: 'tagged_at', order: 'DESC' };
let multiselectInstances = null;

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

/**
 * Инициализация страницы
 */
async function initializePage() {
    // Инициализируем multiselect компоненты
    multiselectInstances = initMultiselects();

    await loadFilterOptions();
    await loadStateFromURL();
    setupEventListeners();
    await loadTags();
}

/**
 * Загрузка доступных значений для фильтров
 */
async function loadFilterOptions() {
    try {
        const response = await fetchWithRetry('api/filters.php', {
            headers: {
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        if (result.success) {
            const { departments, managers } = result.data;

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

            // Заполняем multiselect менеджеров
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
                    { name: 'tag_types[]', value: 'good', label: '✅ Хорошо' },
                    { name: 'tag_types[]', value: 'bad', label: '❌ Плохо' },
                    { name: 'tag_types[]', value: 'question', label: '❓ Вопрос' }
                ];
                tagsMS.setOptions(tagOptions);
            }
        }
    } catch (error) {
        console.error('Ошибка загрузки фильтров:', error);
    }
}

/**
 * Восстановление состояния из URL
 */
async function loadStateFromURL() {
    const params = new URLSearchParams(window.location.search);

    // Восстанавливаем фильтры
    currentFilters = {};

    // Восстанавливаем multiselect компоненты
    if (multiselectInstances) {
        // Теги
        const tagTypes = params.get('tag_types');
        if (tagTypes) {
            currentFilters['tag_types'] = tagTypes;
            const tagsMS = multiselectInstances.get('tags-multiselect');
            if (tagsMS) {
                const tagsArray = tagTypes.split(',');
                tagsMS.setValues(tagsArray);
            }
        }

        // Отделы
        const departments = params.get('departments');
        if (departments) {
            currentFilters['departments'] = departments;
            const departmentMS = multiselectInstances.get('department-multiselect');
            if (departmentMS) {
                const deptArray = departments.split(',');
                departmentMS.setValues(deptArray);
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
    }

    // Даты
    const dateFrom = params.get('date_from');
    if (dateFrom) {
        currentFilters['date_from'] = dateFrom;
        document.getElementById('date_from').value = dateFrom;
    }

    const dateTo = params.get('date_to');
    if (dateTo) {
        currentFilters['date_to'] = dateTo;
        document.getElementById('date_to').value = dateTo;
    }

    // Восстанавливаем страницу
    const page = params.get('page');
    if (page) {
        currentPage = parseInt(page);
    }
}

/**
 * Сохранение состояния в URL
 */
function saveStateToURL() {
    const params = new URLSearchParams();

    // Сохраняем фильтры
    for (let [key, value] of Object.entries(currentFilters)) {
        if (value) {
            params.set(key, value);
        }
    }

    // Сохраняем страницу
    if (currentPage !== 1) {
        params.set('page', currentPage);
    }

    const newURL = params.toString() ? `?${params.toString()}` : window.location.pathname;
    window.history.replaceState({}, '', newURL);
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
        saveStateToURL();
        loadTags();
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
        saveStateToURL();
        loadTags();
    });

    // Сортировка по колонкам
    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const sortBy = this.getAttribute('data-sort');

            if (currentSort.by === sortBy) {
                currentSort.order = currentSort.order === 'DESC' ? 'ASC' : 'DESC';
            } else {
                currentSort.by = sortBy;
                currentSort.order = 'DESC';
            }

            // Обновляем стрелки сортировки
            document.querySelectorAll('th[data-sort]').forEach(header => {
                const icon = header.querySelector('.sort-icon');
                if (icon) {
                    icon.textContent = '↕';
                }
            });

            const icon = this.querySelector('.sort-icon');
            if (icon) {
                icon.textContent = currentSort.order === 'DESC' ? '↓' : '↑';
            }

            saveStateToURL();
            loadTags();
        });
    });
}

/**
 * Получение фильтров из формы
 */
function getFiltersFromForm() {
    const filters = {};

    // Даты
    const dateFrom = document.getElementById('date_from').value;
    if (dateFrom) filters['date_from'] = dateFrom;

    const dateTo = document.getElementById('date_to').value;
    if (dateTo) filters['date_to'] = dateTo;

    // Обработка multiselect компонентов
    if (multiselectInstances) {
        // Теги
        const tagsMS = multiselectInstances.get('tags-multiselect');
        if (tagsMS) {
            const tags = tagsMS.getValues();
            if (tags.length > 0) {
                filters['tag_types'] = tags.join(',');
            }
        }

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
    }

    return filters;
}

/**
 * Загрузка списка тегированных звонков
 */
async function loadTags() {
    const tbody = document.getElementById('calls-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="loading">Загрузка данных...</td></tr>';

    try {
        // Формируем URL с параметрами
        const params = new URLSearchParams({
            ...currentFilters,
            page: currentPage,
            per_page: 20
        });

        console.log('🔍 Отправка фильтров:', currentFilters);
        console.log('📡 API URL:', `api/tags.php?${params}`);

        const response = await fetchWithRetry(`api/tags.php?${params}`, {
            headers: {
                'Accept': 'application/json'
            }
        });
        console.log('📥 Response status:', response.status);
        console.log('📥 Response headers:', response.headers);

        const responseText = await response.text();
        console.log('📥 Response text (raw):', responseText);

        let result;
        try {
            result = JSON.parse(responseText);
            console.log('📥 Response JSON:', result);
        } catch (e) {
            console.error('❌ Не удалось распарсить JSON:', e);
            console.error('Raw response:', responseText);
            tbody.innerHTML = '<tr><td colspan="9" class="error">Ошибка формата ответа API</td></tr>';
            return;
        }

        if (result.success) {
            console.log('✅ Данные успешно загружены:', result.data.length, 'тегов');
            renderTags(result.data);
            renderPagination(result.pagination);
            updateStats(result.pagination);
        } else {
            console.error('❌ API вернул ошибку:', result.error);
            tbody.innerHTML = `<tr><td colspan="9" class="error">Ошибка: ${result.error || 'Неизвестная ошибка'}</td></tr>`;
        }
    } catch (error) {
        console.error('❌ Ошибка загрузки тегов:', error);
        tbody.innerHTML = '<tr><td colspan="9" class="error">Ошибка подключения к серверу: ' + error.message + '</td></tr>';
    }
}

/**
 * Отрисовка списка тегированных звонков
 */
function renderTags(tags) {
    const tbody = document.getElementById('calls-tbody');

    if (tags.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Тегированных звонков не найдено</td></tr>';
        return;
    }

    tbody.innerHTML = tags.map(tag => `
        <tr>
            <td class="text-center">
                <input type="checkbox" class="call-checkbox" data-callid="${tag.callid}">
            </td>
            <td class="tag-cell" title="${formatTagTitle(tag.tag_type, tag.note)}">
                ${formatTag(tag.tag_type)}
            </td>
            <td class="note-cell" title="${escapeHtml(tag.note || '')}">${formatNote(tag.note)}</td>
            <td class="employee-cell">${escapeHtml(tag.employee_name || '-')}</td>
            <td>${formatCallResult(tag.call_result)}</td>
            <td>${formatDateTime(tag.started_at_utc)}</td>
            <td>${escapeHtml(tag.client_phone || '-')}</td>
            <td class="actions-cell">
                <a href="call_evaluation.php?callid=${encodeURIComponent(tag.callid)}"
                   class="btn btn-primary btn-sm">
                    Открыть
                </a>
            </td>
            <td class="department-cell">${escapeHtml(tag.department || '-')}</td>
        </tr>
    `).join('');
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
    saveStateToURL();
    loadTags();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Форматирование тега
 */
function formatTag(tagType) {
    const tagEmojis = {
        'good': '✅',
        'bad': '❌',
        'question': '❓'
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
        'question': 'Вопрос'
    };

    let title = `Тег: ${tagNames[tagType]}`;
    if (tagNote) {
        title += `\nЗаметка: ${tagNote}`;
    }
    return title;
}

/**
 * Форматирование заметки
 */
function formatNote(note) {
    if (!note || note.trim() === '') return '—';

    const maxLength = 50;
    const text = note.trim();

    if (text.length > maxLength) {
        return escapeHtml(text.substring(0, maxLength)) + '...';
    }

    return escapeHtml(text);
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
 * Форматирование результата звонка
 */
function formatCallResult(result) {
    if (!result) return '-';

    let cleanResult = result.replace(/^Результат:\s*/i, '').trim();
    cleanResult = cleanResult.replace(/\s+звонок$/i, '');
    cleanResult = cleanResult.replace(/\s+выполнена$/i, '');

    let badgeClass = 'badge-info';
    let icon = '';
    const resultLower = cleanResult.toLowerCase();

    if (resultLower.includes('квалифик')) {
        badgeClass = 'badge-success';
        icon = '📋 ';
    } else if (resultLower.includes('материал') || resultLower.includes('отправ')) {
        badgeClass = 'badge-success';
        icon = '📤 ';
    } else if (resultLower.includes('показ')) {
        badgeClass = 'badge-success';
        icon = '🏠 ';
    } else if (resultLower.includes('перезвон')) {
        badgeClass = 'badge-info';
        icon = '📞 ';
    } else if (resultLower.includes('отказ')) {
        badgeClass = 'badge-danger';
        icon = '❌ ';
    } else if (resultLower.includes('не дозвон')) {
        badgeClass = 'badge-secondary';
        icon = '📵 ';
    } else if (resultLower.includes('личн') || resultLower.includes('нерабоч')) {
        badgeClass = 'badge-secondary';
        icon = '👤 ';
    }

    return `<span class="badge ${badgeClass}">${icon}${escapeHtml(cleanResult)}</span>`;
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
