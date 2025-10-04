/**
 * JavaScript для страницы списка звонков
 */

// Глобальные переменные
let currentPage = 1;
let currentFilters = {};
let currentSort = { by: 'started_at_utc', order: 'DESC' };

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

/**
 * Инициализация страницы
 */
async function initializePage() {
    await loadFilterOptions();
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

            // Заполняем селект отделов
            const departmentSelect = document.getElementById('department');
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept;
                option.textContent = dept;
                departmentSelect.appendChild(option);
            });

            // Заполняем селект менеджеров
            const managerSelect = document.getElementById('manager');
            managers.forEach(manager => {
                const option = document.createElement('option');
                option.value = manager;
                option.textContent = manager;
                managerSelect.appendChild(option);
            });

            // Типы звонков уже заданы в HTML, но можем добавить динамические
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
 * Настройка обработчиков событий
 */
function setupEventListeners() {
    // Фильтры
    const filtersForm = document.getElementById('filters-form');
    filtersForm.addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        currentFilters = getFiltersFromForm();
        loadCalls();
    });

    // Сброс фильтров
    document.getElementById('reset-filters').addEventListener('click', function() {
        filtersForm.reset();
        currentPage = 1;
        currentFilters = {};
        loadCalls();
    });

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

    for (let [key, value] of formData.entries()) {
        if (value) {
            filters[key] = value;
        }
    }

    return filters;
}

/**
 * Загрузка списка звонков
 */
async function loadCalls() {
    const tbody = document.getElementById('calls-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="loading">Загрузка данных...</td></tr>';

    try {
        // Формируем URL с параметрами
        const params = new URLSearchParams({
            ...currentFilters,
            page: currentPage,
            per_page: 20,
            sort_by: currentSort.by,
            sort_order: currentSort.order
        });

        const response = await fetch(`api/calls.php?${params}`);
        const result = await response.json();

        if (result.success) {
            renderCalls(result.data);
            renderPagination(result.pagination);
            updateStats(result.pagination);
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="error">Ошибка загрузки данных</td></tr>';
        }
    } catch (error) {
        console.error('Ошибка загрузки звонков:', error);
        tbody.innerHTML = '<tr><td colspan="9" class="error">Ошибка подключения к серверу</td></tr>';
    }
}

/**
 * Отрисовка списка звонков
 */
function renderCalls(calls) {
    const tbody = document.getElementById('calls-tbody');

    if (calls.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Звонки не найдены</td></tr>';
        return;
    }

    tbody.innerHTML = calls.map(call => `
        <tr>
            <td>${formatDateTime(call.started_at_utc)}</td>
            <td>${escapeHtml(call.employee_name || '-')}</td>
            <td>${escapeHtml(call.department || '-')}</td>
            <td>${escapeHtml(call.client_phone || '-')}</td>
            <td>${formatDuration(call.duration_sec)}</td>
            <td>${formatCallType(call.call_type)}</td>
            <td>${formatRating(call.score_overall)}</td>
            <td>${formatEmotionTone(call.emotion_tone, call.conversion_probability)}</td>
            <td>
                <a href="call_evaluation.php?callid=${encodeURIComponent(call.callid)}"
                   class="btn btn-primary btn-sm">
                    Открыть
                </a>
            </td>
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
    if (!seconds) return '-';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}м ${secs}с`;
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
 * Форматирование оценки (score_overall от 0 до 10)
 */
function formatRating(score) {
    if (score === null || score === undefined) return '-';

    const scoreNum = parseFloat(score);
    const percentage = Math.round((scoreNum / 10) * 100);
    let className = 'rating-low';

    if (percentage >= 80) {
        className = 'rating-high';
    } else if (percentage >= 60) {
        className = 'rating-medium';
    }

    return `<span class="rating-badge ${className}">${scoreNum.toFixed(1)}/10</span>`;
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
 * Форматирование результата звонка (используем conversion_probability)
 */
function formatCallResult(result, isSuccessful) {
    // Пытаемся использовать имеющиеся данные
    if (!result && !isSuccessful) return '-';

    // Если есть результат, отображаем его
    if (result) {
        let badgeClass = 'badge-info';

        // Определяем класс по ключевым словам
        if (result.includes('показ')) badgeClass = 'badge-success';
        else if (result.includes('перезвон')) badgeClass = 'badge-warning';
        else if (result.includes('отказ')) badgeClass = 'badge-danger';

        return `<span class="badge ${badgeClass}">${escapeHtml(result)}</span>`;
    }

    // Иначе используем isSuccessful
    const badgeClass = isSuccessful ? 'badge-success' : 'badge-danger';
    const text = isSuccessful ? 'Успешный' : 'Неуспешный';
    return `<span class="badge ${badgeClass}">${text}</span>`;
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
